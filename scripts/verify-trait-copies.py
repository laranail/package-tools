#!/usr/bin/env python3
#
# `laranail/console` holds the canonical `SupportsNamespacedNames`; a few packages
# carry their own copy for reasons that are supposed to hold. Each carrier's
# conformance test explains, in prose, which packages those are and why.
#
# That prose went wrong twice in one day.
#
#   laranail/package-tools#39 corrected it BECAUSE db-tools still shipped a copy
#   while the paragraph said the copy was gone. laranail/db-tools#39 then deleted
#   the copy, which made the corrected text wrong again, in the other direction.
#   Four repositories had to be edited together to agree, twice.
#
# Nothing failed either time. The conformance tests assert the trait's BEHAVIOUR,
# which is identical whether a package carries a copy or imports one, so the
# paragraph describing the tree is unguarded by construction. Every check was
# green while the documentation asserted the opposite of the repository.
#
# Three things follow, and they are what this script checks.
#
#   1. A CARRIER MUST DECLARE ITSELF, machine-readably.
#
#      Prose cannot be checked, so the checkable thing is a declaration beside
#      it. A package that carries a copy declares one of:
#
#          @trait-copy-canonical               (console: the copy others import)
#          @trait-copy-reason <slug>           (everyone else)
#
#      in its conformance test's docblock. The prose stays prose -- for a human
#      deciding whether to add or remove a copy -- and the declaration is what
#      this script reads. They can still disagree with each other; they cannot
#      both disagree with the tree unnoticed, because the count the prose states
#      is derivable from the declarations this script collects and prints.
#
#   2. THE REASON MUST BE ONE A MACHINE CAN FALSIFY.
#
#      This is the actual fix, not the bookkeeping. The two reasons that survived
#      contact with reality are facts about a manifest:
#
#          no-laranail-requirement   -- `require` contains no `laranail/*` entry
#          php-floor-below-console   -- this package's PHP floor is lower
#
#      The reason that rotted was "db-tools documents an independence invariant".
#      Nothing could ever have failed on it: it described a decision, not a
#      condition, so it stayed true-sounding after the invariant it named had
#      stopped holding -- db-tools took `laranail/package-tools` into `require`
#      three weeks before anybody noticed the sentence.
#
#      So an unrecognised reason slug is an ERROR here, not a warning. A carrier
#      that cannot say why in terms of its own manifest has no business carrying
#      a copy, and a reason that is checked is a reason that gets removed when it
#      expires.
#
#   3. A DECLARATION MUST EXPIRE WITH THE COPY.
#
#      A package that declares a reason and no longer carries a copy is the db-tools
#      case exactly: the justification outliving the thing it justified. Checked in
#      both directions.
#
#   4. A FILE OF THE SAME NAME IS NOT NECESSARILY A COPY.
#
#      Found by this script's own first run, which reported `laranail/impersonator`
#      as an undeclared carrier. It is not one. Its
#      `Concerns/SupportsNamespacedNames.php` IMPORTS console's trait and adds only
#      its own constructor-driven signature handling -- it composes the canonical
#      copy rather than duplicating the reflection write, and it requires
#      `laranail/console` to do so.
#
#      Detecting carriers by filename alone would have reported a false positive
#      that read exactly like a real finding, and acting on it would have added
#      impersonator to four packages' prose as a carrier it is not. So a
#      same-named file counts as a copy only when it does NOT import
#      CANONICAL_FQN.
#
# Reads the REMOTE tree by default, so a stale local checkout cannot make it pass
# and it needs no particular fetch depth in CI. Pass --from-checkout to read a
# local family instead, which is how you run it while editing.
#
# Non-vacuity is asserted before anything else: a scan that finds no carrier at
# all, or no conformance test at all, has proved nothing about an empty set and
# must not report success. That failure mode is why this file exists.
#
# Usage:
#   verify-trait-copies.py                          # the whole org, over the API
#   verify-trait-copies.py --from-checkout ../..    # a local packages/ directory
#   verify-trait-copies.py --org laranail
#
# Needs GITHUB_TOKEN for the API mode's rate limit; every repo it reads is public,
# so no additional secret is required.

from __future__ import annotations

import argparse
import json
import os
import re
import sys
import subprocess
from pathlib import Path

TRAIT_FILE = "Concerns/SupportsNamespacedNames.php"
CONFORMANCE = re.compile(r"NamespacedNamesConformanceTest\.php$")

# The canonical trait, by fully-qualified name. A package whose same-named file
# IMPORTS this is composing the canonical copy, not duplicating it, and is not a
# carrier -- see note 4 in the header.
CANONICAL_FQN = (
    r"Simtabi\\Laranail\\Console\\Tools\\Commands\\Concerns\\SupportsNamespacedNames"
)
COMPOSES = re.compile(r"use\s+" + CANONICAL_FQN + r"\b")

CANONICAL = re.compile(r"@trait-copy-canonical\b")
REASON = re.compile(r"@trait-copy-reason\s+([a-z0-9-]+)")

# A reason is admissible only if this script can falsify it. See note 2 above.
KNOWN_REASONS = ("no-laranail-requirement", "php-floor-below-console")

API = "https://api.github.com"


# --------------------------------------------------------------------------- io


class Unreachable(RuntimeError):
    """The API could not be read at all. Distinct from "read it and found
    nothing", because the second is a finding and the first is not: a network or
    trust failure that returned an empty set would otherwise pass every check
    below it, which is the vacuity this script refuses."""


class NotFound(RuntimeError):
    """A 404. A real answer -- an empty repository has no tree."""


def _fetch(url: str, *, accept_json: bool = True) -> str:
    """Transport is `curl`, not urllib, for the same reason
    `verify-tag-currency.sh` uses `gh api`: the CLI honours the proxy and trust
    configuration of whatever it runs on. Python's urllib does not, and behind a
    filtering proxy it fails two different ways -- an unverifiable certificate,
    then a truncated body raised as IncompleteRead -- neither of which says
    anything about this repository.

    The HTTP status is read from `-w`, not inferred from curl's exit code. Found
    the hard way: `laranail/scrambler` is an EMPTY repository, whose tree endpoint
    answers 409, and `curl -f` surfaced that as exit 56 rather than its documented
    22 for an HTTP error. Keying on the exit code aborted the whole run over a
    repository that simply has no commits -- a finding, not a failure."""
    cmd = ["curl", "-sS", "--max-time", "60", "-w", "\n%{http_code}"]
    if accept_json:
        cmd += ["-H", "Accept: application/vnd.github+json"]
    token = os.environ.get("GITHUB_TOKEN") or os.environ.get("GH_TOKEN")
    if token:
        cmd += ["-H", f"Authorization: Bearer {token}"]
    cmd.append(url)

    try:
        p = subprocess.run(cmd, capture_output=True, text=True, timeout=90)
    except (OSError, subprocess.TimeoutExpired) as e:
        raise Unreachable(f"{url}: {e}") from e
    if p.returncode != 0 and not p.stdout:
        raise Unreachable(f"{url}: curl exit {p.returncode}: {p.stderr.strip()}")

    body, _, status = p.stdout.rpartition("\n")

    if status in ("404", "409"):
        # 404 absent, 409 empty repository. Both mean "nothing here to read",
        # which is an answer about this package, not a failure of the run.
        raise NotFound(f"{url} (HTTP {status})")
    if status != "200":
        # Anything else -- 401, 403 rate limit, 5xx -- is the run failing to read
        # the org. It must not degrade into an empty set. See Unreachable.
        raise Unreachable(f"{url}: HTTP {status}")
    return body


def _get(url: str) -> object:
    body = _fetch(url)
    try:
        return json.loads(body)
    except json.JSONDecodeError as e:
        raise Unreachable(f"{url}: response was not JSON ({e})") from e


class Source:
    """Where the facts come from. Two implementations, one interface, so the
    local and CI runs cannot drift into checking different things."""

    def packages(self) -> list[str]:
        raise NotImplementedError

    def paths(self, pkg: str) -> list[str]:
        raise NotImplementedError

    def read(self, pkg: str, path: str) -> str | None:
        raise NotImplementedError


class Remote(Source):
    def __init__(self, org: str) -> None:
        self.org = org
        self._trees: dict[str, list[str]] = {}
        self._branches: dict[str, str] = {}

    def packages(self) -> list[str]:
        out, page = [], 1
        while True:
            batch = _get(f"{API}/orgs/{self.org}/repos?per_page=100&page={page}")
            if not batch:
                break
            out += [r["name"] for r in batch if not r.get("archived")]
            if len(batch) < 100:
                break
            page += 1
        return sorted(out)

    def paths(self, pkg: str) -> list[str]:
        if pkg not in self._trees:
            try:
                tree = _get(
                    f"{API}/repos/{self.org}/{pkg}/git/trees/"
                    f"{self._branch(pkg)}?recursive=1"
                )
                self._trees[pkg] = [e["path"] for e in tree.get("tree", [])]
            except NotFound:
                # An empty repository has no tree. Not a carrier, not an error.
                self._trees[pkg] = []
        return self._trees[pkg]

    def _branch(self, pkg: str) -> str:
        if pkg not in self._branches:
            self._branches[pkg] = _get(f"{API}/repos/{self.org}/{pkg}")["default_branch"]
        return self._branches[pkg]

    def read(self, pkg: str, path: str) -> str | None:
        try:
            url = (
                f"https://raw.githubusercontent.com/{self.org}/{pkg}/"
                f"{self._branch(pkg)}/{path}"
            )
            return _fetch(url, accept_json=False)
        except NotFound:
            return None


class Checkout(Source):
    """A local `packages/` directory. Directory names are repo slugs by
    convention, but several entries there are not packages at all -- parked
    checkouts, non-git scratch -- so only directories with a composer.json and a
    git dir count."""

    def __init__(self, root: Path) -> None:
        self.root = root

    def packages(self) -> list[str]:
        return sorted(
            d.name
            for d in self.root.iterdir()
            if d.is_dir() and (d / "composer.json").is_file() and (d / ".git").exists()
        )

    def paths(self, pkg: str) -> list[str]:
        base = self.root / pkg
        out = []
        for sub in ("src", "tests"):
            p = base / sub
            if p.is_dir():
                out += [str(f.relative_to(base)) for f in p.rglob("*.php")]
        return out

    def read(self, pkg: str, path: str) -> str | None:
        f = self.root / pkg / path
        return f.read_text(encoding="utf-8", errors="replace") if f.is_file() else None


# ---------------------------------------------------------------------- reasons


def php_floor(manifest: str | None) -> tuple[int, ...] | None:
    if not manifest:
        return None
    try:
        constraint = json.loads(manifest).get("require", {}).get("php", "")
    except json.JSONDecodeError:
        return None
    # The floor is the lowest bound the constraint admits, which for the shapes
    # this family writes (`^8.3`, `^8.4.1 || ^8.5`) is the first one mentioned.
    m = re.search(r"(\d+)\.(\d+)(?:\.(\d+))?", constraint)
    return tuple(int(g or 0) for g in m.groups()) if m else None


def laranail_requirements(manifest: str | None) -> list[str]:
    if not manifest:
        return []
    try:
        require = json.loads(manifest).get("require", {})
    except json.JSONDecodeError:
        return []
    return sorted(k for k in require if k.startswith("laranail/"))


def check_reason(slug: str, manifest: str | None, console_floor) -> str | None:
    """None when the reason holds; otherwise why it does not."""
    if slug == "no-laranail-requirement":
        deps = laranail_requirements(manifest)
        if deps:
            return f"declares laranail requirements {deps}, so the reason no longer holds"
        return None

    if slug == "php-floor-below-console":
        mine = php_floor(manifest)
        if mine is None:
            return "no parseable `require.php`, so the floor cannot be compared"
        if console_floor is None:
            return "console's own floor could not be read, so this cannot be checked"
        if mine >= console_floor:
            v = ".".join(map(str, mine))
            c = ".".join(map(str, console_floor))
            return f"floor {v} is not below console's {c}, so the reason no longer holds"
        return None

    return f"unrecognised reason -- must be one of {', '.join(KNOWN_REASONS)}"


# ------------------------------------------------------------------------- main


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--org", default="laranail")
    ap.add_argument("--from-checkout", metavar="DIR", default=None)
    args = ap.parse_args()

    src: Source = (
        Checkout(Path(args.from_checkout).resolve())
        if args.from_checkout
        else Remote(args.org)
    )

    console_floor = php_floor(src.read("console", "composer.json"))

    carriers: dict[str, str] = {}     # pkg -> path of its trait copy
    composers: dict[str, str] = {}    # pkg -> path of a file composing the canonical
    declared: dict[str, str] = {}     # pkg -> reason slug, or "canonical"
    tests: dict[str, str] = {}        # pkg -> path of its conformance test
    problems: list[str] = []

    for pkg in src.packages():
        paths = src.paths(pkg)

        for p in paths:
            if p.endswith(TRAIT_FILE):
                # Same name, but composing the canonical trait rather than
                # duplicating it. Note 4: this is where the false positive was.
                if COMPOSES.search(src.read(pkg, p) or ""):
                    composers[pkg] = p
                else:
                    carriers[pkg] = p
                break

        for p in paths:
            if CONFORMANCE.search(p):
                tests[pkg] = p
                body = src.read(pkg, p) or ""
                if CANONICAL.search(body):
                    declared[pkg] = "canonical"
                else:
                    m = REASON.search(body)
                    if m:
                        declared[pkg] = m.group(1)
                break

    # Non-vacuity first. Everything below is a statement about these two sets,
    # and a statement about an empty set reads as a guarantee.
    if not carriers:
        print("FAIL: found no package carrying the trait at all -- this proved nothing.")
        print("      Expected at least laranail/console to carry the canonical copy.")
        return 1
    if not tests:
        print("FAIL: found no conformance test in any package -- this proved nothing.")
        return 1

    for pkg, path in sorted(carriers.items()):
        if pkg not in tests:
            problems.append(
                f"{pkg}: carries {path} but ships no conformance test, so nothing "
                f"asserts the copy still agrees with the canonical one"
            )
            continue
        if pkg not in declared:
            problems.append(
                f"{pkg}: carries {path} but {tests[pkg]} declares neither "
                f"@trait-copy-canonical nor @trait-copy-reason"
            )
            continue
        slug = declared[pkg]
        if slug == "canonical":
            continue
        why = check_reason(slug, src.read(pkg, "composer.json"), console_floor)
        if why:
            problems.append(f"{pkg}: declares @trait-copy-reason {slug}, but {why}")

    # A reason that outlives its copy is the db-tools failure, exactly.
    for pkg, slug in sorted(declared.items()):
        if pkg not in carriers:
            problems.append(
                f"{pkg}: {tests[pkg]} still declares "
                f"{'@trait-copy-canonical' if slug == 'canonical' else f'@trait-copy-reason {slug}'}, "
                f"but the package no longer carries a copy -- delete the declaration"
            )

    canonical = sorted(p for p, s in declared.items() if s == "canonical")
    if len(canonical) != 1:
        problems.append(
            f"expected exactly one package to declare @trait-copy-canonical, found "
            f"{canonical or 'none'}"
        )

    others = sorted(p for p in carriers if declared.get(p) != "canonical")
    print(f"canonical copy : {canonical[0] if canonical else '<none>'}")
    print(f"other carriers : {len(others)} -- {', '.join(others) or '<none>'}")
    for pkg in others:
        print(f"  {pkg:<22} {declared.get(pkg, '<undeclared>')}")
    if composers:
        print(
            f"composes it    : {', '.join(sorted(composers))}  "
            f"(same filename, imports the canonical trait -- not a carrier)"
        )

    if problems:
        print(f"\n{len(problems)} problem(s):")
        for p in problems:
            print(f"  - {p}")
        print(
            "\nThe prose in each conformance test names these packages and counts "
            "them.\nWhen this list changes, every carrier's paragraph changes with "
            "it, in one sweep."
        )
        return 1

    print("\nOK: every carrier declares a reason this script can falsify, and each holds.")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Unreachable as e:
        print(f"FAIL: could not read the org -- this proved nothing.\n      {e}")
        sys.exit(2)
