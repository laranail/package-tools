#!/usr/bin/env python3
"""Reset a repository's release tags on purpose: move one tag, or replace them all with one.

Release tags are final. A version tag is cached the first time anything fetches it
(Packagist, Composer's dist cache, the Go proxy and sum.golang.org, npm), and moving
or deleting it afterwards only makes those caches disagree with git. The
"Protect release tags" ruleset blocks it for that reason. This script is the one
sanctioned way through, for a planned history reset or re-tag the owner approved.

Two modes:

  --move vX.Y.Z --to SHA    re-point one existing tag (and its src/vX.Y.Z alias, if
                            there is one). The ref is updated in place, so its GitHub
                            release stays published rather than reverting to a draft.
  --fresh vX.Y.Z --to SHA   delete every v* and src/v* version tag and their GitHub
                            releases, then create the one fresh tag.

Either way the tag lands on GitHub as a push, so the tag-driven release workflow
runs again on its own; nothing else is needed.

Safety:

  - Dry run by default. It lists every tag and release it would delete or move, with
    SHAs, and every cached copy that would then disagree with git.
  - --apply also needs --confirm OWNER/REPO, typed exactly, or it refuses.
  - SHA must be on the default branch (GitHub's compare endpoint), or it refuses.
  - The "Protect release tags" ruleset is disabled for the work and re-enabled in a
    `finally`, even when the work fails. Its state is printed at the end.
  - Only version tags (v1.2.3, src/v1.2.3, with an optional -pre-release suffix)
    are ever deleted. Every other tag is listed as untouched.

PACKAGIST_BASE overrides https://repo.packagist.org for the cache probe (tests).

Usage:
    reset-release-tags.py OWNER/REPO (--move TAG | --fresh TAG) --to SHA [--apply --confirm OWNER/REPO]
"""

from __future__ import annotations

import json
import os
import re
import subprocess
import sys
import urllib.error
import urllib.request

RULESET = "Protect release tags"
VERSION_TAG = re.compile(r"^(src/)?v\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$")
SHA = re.compile(r"^[0-9a-f]{7,40}$")


def usage() -> int:
    print(__doc__.split("Usage:")[1].strip(), file=sys.stderr)
    return 2


def gh(*args: str, stdin: str | None = None) -> str:
    result = subprocess.run(["gh", *args], input=stdin, capture_output=True, text=True, check=False)
    if result.returncode != 0:
        raise RuntimeError(result.stderr.strip() or f"gh {' '.join(args)} failed")
    return result.stdout


def gh_pages(path: str) -> list:
    """Every item of a paginated array endpoint. `gh --paginate` prints one array per page."""
    text = gh("api", "--paginate", path)
    decoder, items, i = json.JSONDecoder(), [], 0
    while i < len(text):
        if text[i].isspace():
            i += 1
            continue
        page, i = decoder.raw_decode(text, i)
        items.extend(page)
    return items


def tags(repo: str) -> dict[str, str]:
    """Tag name -> commit SHA, dereferencing annotated tags."""
    out = {}
    for ref in gh_pages(f"repos/{repo}/git/matching-refs/tags"):
        name = ref["ref"].removeprefix("refs/tags/")
        sha = ref["object"]["sha"]
        if ref["object"]["type"] == "tag":
            sha = json.loads(gh("api", f"repos/{repo}/git/tags/{sha}"))["object"]["sha"]
        out[name] = sha
    return out


def on_default_branch(repo: str, sha: str) -> tuple[str, str | None]:
    """(default branch, full SHA) when SHA is on it, else (default branch, None)."""
    branch = json.loads(gh("api", f"repos/{repo}"))["default_branch"]
    compare = json.loads(gh("api", f"repos/{repo}/compare/{branch}...{sha}"))
    if compare["status"] not in ("behind", "identical"):
        return branch, None
    return branch, compare["merge_base_commit"]["sha"]


def packagist(repo: str) -> str:
    base = os.environ.get("PACKAGIST_BASE", "https://repo.packagist.org")
    url = f"{base}/p2/{repo.lower()}.json"
    try:
        with urllib.request.urlopen(url, timeout=10) as response:
            return f"{getattr(response, 'status', 200) or 200} (listed)"
    except urllib.error.HTTPError as e:
        return f"{e.code} (not listed)" if e.code == 404 else f"{e.code}"
    except (urllib.error.URLError, OSError) as e:
        return f"unknown ({e})"


def find_ruleset(repo: str) -> dict | None:
    try:
        rulesets = json.loads(gh("api", f"repos/{repo}/rulesets?includes_parents=false"))
    except RuntimeError as e:
        if "403" in str(e) or "Upgrade to GitHub Pro" in str(e):
            return None
        raise
    return next((r for r in rulesets if r["name"] == RULESET and r["target"] == "tag"), None)


def set_enforcement(repo: str, ruleset_id: int, enforcement: str) -> None:
    gh("api", "-X", "PUT", f"repos/{repo}/rulesets/{ruleset_id}", "--input", "-",
       stdin=json.dumps({"enforcement": enforcement}))


def parse(args: list[str]) -> dict | None:
    opts = {"apply": "--apply" in args}
    taking = {"--move", "--fresh", "--to", "--confirm"}
    positional = []
    i = 0
    while i < len(args):
        a = args[i]
        if a in taking:
            if i + 1 >= len(args):
                return None
            opts[a[2:]] = args[i + 1]
            i += 2
            continue
        if a.startswith("--") and a != "--apply":
            return None
        if a != "--apply":
            positional.append(a)
        i += 1
    if len(positional) != 1 or "/" not in positional[0]:
        return None
    if ("move" in opts) == ("fresh" in opts) or "to" not in opts:
        return None
    opts["repo"] = positional[0]
    return opts


def main(argv: list[str]) -> int:
    opts = parse(argv[1:])
    if opts is None:
        return usage()
    repo, apply = opts["repo"], opts["apply"]
    mode = "move" if "move" in opts else "fresh"
    tag = opts[mode]

    if not VERSION_TAG.match(tag) or tag.startswith("src/"):
        print(f"{tag!r} is not a version tag (vX.Y.Z).", file=sys.stderr)
        return 2
    if not SHA.match(opts["to"]):
        print(f"{opts['to']!r} is not a commit SHA.", file=sys.stderr)
        return 2
    if apply and opts.get("confirm") != repo:
        print(f"Refusing: --apply needs --confirm {repo}, typed exactly.", file=sys.stderr)
        return 2

    branch, sha = on_default_branch(repo, opts["to"])
    if sha is None:
        print(f"Refusing: {opts['to']} is not on {repo}'s default branch {branch}.", file=sys.stderr)
        return 1

    current = tags(repo)
    versions = {n: s for n, s in current.items() if VERSION_TAG.match(n)}
    others = sorted(n for n in current if n not in versions)

    if mode == "move":
        if tag not in versions:
            print(f"Refusing: {tag} does not exist on {repo}; use --fresh, or push it normally.", file=sys.stderr)
            return 1
        moves = [n for n in (tag, f"src/{tag}") if n in versions]
        deletes, releases = [], []
    else:
        moves = []
        deletes = sorted(versions)
        releases = [r for r in gh_pages(f"repos/{repo}/releases") if VERSION_TAG.match(r["tag_name"])]

    print(f"{repo}: {mode} {tag} -> {sha[:12]} (on {branch})")
    for n in moves:
        print(f"  move     {n}  {versions[n][:12]} -> {sha[:12]}")
    for n in deletes:
        print(f"  delete   {n}  {versions[n][:12]}")
    for r in releases:
        print(f"  delete   release {r['tag_name']} (id {r['id']}{', draft' if r.get('draft') else ''})")
    if mode == "fresh":
        print(f"  create   {tag}  {sha[:12]}")
    for n in others:
        print(f"  keep     {n}  {current[n][:12]} (not a version tag)")

    changed = moves or deletes
    print("\nCached copies this contradicts:")
    print(f"  Packagist  https://repo.packagist.org/p2/{repo.lower()}.json -> {packagist(repo)}")
    print("  Composer   every dist cache keeps the old archive for "
          f"{', '.join(changed) or 'these tags'} until `composer clear-cache`.")
    if any(n.startswith("src/") for n in moves + deletes):
        print("  Go proxy   proxy.golang.org and sum.golang.org keep any src/v* version they fetched, for good.")

    if not apply:
        print(f"\nDry run. Nothing changed. Re-run with --apply --confirm {repo}.")
        return 0

    ruleset = find_ruleset(repo)
    failed = False
    try:
        if ruleset:
            set_enforcement(repo, ruleset["id"], "disabled")
            print(f"\nDisabled ruleset {ruleset['id']} ({RULESET}).")
        else:
            print(f"\nNo {RULESET!r} ruleset to disable.")
        for n in moves:
            gh("api", "-X", "PATCH", f"repos/{repo}/git/refs/tags/{n}", "--input", "-",
               stdin=json.dumps({"sha": sha, "force": True}))
            print(f"moved    {n} -> {sha[:12]}")
        for r in releases:
            gh("api", "-X", "DELETE", f"repos/{repo}/releases/{r['id']}")
            print(f"deleted  release {r['tag_name']}")
        for n in deletes:
            gh("api", "-X", "DELETE", f"repos/{repo}/git/refs/tags/{n}")
            print(f"deleted  {n}")
        if mode == "fresh":
            gh("api", "-X", "POST", f"repos/{repo}/git/refs", "--input", "-",
               stdin=json.dumps({"ref": f"refs/tags/{tag}", "sha": sha}))
            print(f"created  {tag} -> {sha[:12]}")
    except RuntimeError as e:
        failed = True
        print(f"Failed: {e}", file=sys.stderr)
    finally:
        if ruleset:
            set_enforcement(repo, ruleset["id"], "active")
            state = json.loads(gh("api", f"repos/{repo}/rulesets/{ruleset['id']}"))["enforcement"]
            print(f"Ruleset {ruleset['id']} ({RULESET}): {state}")
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
