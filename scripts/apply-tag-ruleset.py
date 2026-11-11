#!/usr/bin/env python3
"""Apply the family's release-tag ruleset: published version tags cannot move or disappear.

A version tag is cached the first time anything fetches it (Packagist, Composer's
dist cache, the Go proxy, npm), so moving or deleting one afterwards only makes
those caches disagree with git. This ruleset blocks `update` and `deletion` of
`v*` and `src/v*` (the Go subdirectory-module alias), for everyone. Creating a new
tag stays allowed, so releasing is unaffected.

A deliberate move is still possible: disable the ruleset, move the tag, apply it
again. That is the owner's call, after a checklist naming every cached copy the
move contradicts.

Dry run by default: prints the payload, changes nothing. `--apply` creates the
ruleset, or updates it in place when one with this name already exists.

Rulesets are unavailable on private repositories on the free plan; the API
answers 403 and this exits 3 without pretending otherwise.

Usage:
    apply-tag-ruleset.py OWNER/REPO [--apply]
"""

from __future__ import annotations

import json
import subprocess
import sys

NAME = "Protect release tags"
PATTERNS = ["refs/tags/v*", "refs/tags/src/v*"]


def gh(*args: str, stdin: str | None = None) -> str:
    result = subprocess.run(["gh", *args], input=stdin, capture_output=True, text=True)
    if result.returncode != 0:
        raise RuntimeError(result.stderr.strip() or result.stdout.strip())
    return result.stdout


def payload() -> dict:
    return {
        "name": NAME,
        "target": "tag",
        "enforcement": "active",
        "conditions": {"ref_name": {"include": PATTERNS, "exclude": []}},
        "rules": [{"type": "deletion"}, {"type": "update"}],
    }


def main(argv: list[str]) -> int:
    args = argv[1:]
    apply = "--apply" in args
    positional = [a for a in args if not a.startswith("--")]
    if len(positional) != 1 or "/" not in positional[0]:
        print(__doc__.split("Usage:")[1].strip(), file=sys.stderr)
        return 2
    repo = positional[0]
    body = json.dumps(payload())

    if not apply:
        print(f"{repo}: tag ruleset {NAME!r} on {', '.join(PATTERNS)}")
        print("\nDry run. Payload:\n" + json.dumps(payload(), indent=2))
        return 0

    try:
        existing = json.loads(gh("api", f"repos/{repo}/rulesets?includes_parents=false"))
    except RuntimeError as e:
        if "403" in str(e) or "Upgrade to GitHub Pro" in str(e):
            print(f"{repo}: rulesets unavailable (private repository on the free plan).", file=sys.stderr)
            return 3
        raise

    match = next((r for r in existing if r["name"] == NAME and r["target"] == "tag"), None)
    if match:
        result = json.loads(gh("api", "-X", "PUT", f"repos/{repo}/rulesets/{match['id']}", "--input", "-", stdin=body))
        print(f"{repo}: updated tag ruleset {result['id']} in place.")
    else:
        result = json.loads(gh("api", "-X", "POST", f"repos/{repo}/rulesets", "--input", "-", stdin=body))
        print(f"{repo}: created tag ruleset {result['id']}.")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
