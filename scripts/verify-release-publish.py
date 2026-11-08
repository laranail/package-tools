#!/usr/bin/env python3
"""Check that no workflow can leave a GitHub release sitting as a draft.

Deleting a tag does not delete its release -- it reverts it to a DRAFT. The release keeps
its body and its assets and simply stops being visible, and the state outlives the tag:
`laranail/validation` still holds `v1.0.0` and `v1.0.1` as drafts from tags withdrawn on
2026-08-24, neither of which exists on the remote any more.

`gh release edit` leaves that flag exactly as it finds it. So an edit after a tag is deleted
and re-pushed -- which is how the family moves a pre-stable tag wherever `git push --force`
is unavailable -- republishes nothing on a run that still goes GREEN: correct tag, invisible
release.

Nothing else reports that state. The tag resolves, `composer` installs, and
`verify-tag-currency.sh` next door passes, because none of them reads the release. It was
found by reading one: `laranail/validation-js` had a live release sitting invisible after a
green run, and it had to be published by hand (laranail/validation-js#35).

Measured 2026-11-08 across the 286 workflow files in `laranail/packages`: four hand-rolled
call sites in three repositories, every one with green runs behind it.

The fix is one flag, `--draft=false`, on every `gh release edit`. Most repositories never
need it, because they use `softprops/action-gh-release`, which publishes an existing draft
unless its `draft` input is true -- "omit it to publish after upload", in its own words.

A REPOSITORY WITH NO `gh release edit` IS THEREFORE CLEAN, NOT SUSPICIOUS, and that is the
one way this check could lie: if the matcher stopped matching, every repository would pass
and the check would report a clean estate it had not read. So the matcher is driven against
built-in positive and negative controls on every invocation, and the run fails if any control
does not behave. The file floor covers the other direction, a workflows glob that goes empty.

Not checked, deliberately: `gh release create --draft`. A release created as a draft on
purpose, to have assets uploaded before it is published, is the pattern the action's own
documentation describes, so flagging it would report the correct case.

No network and no token: this is a text scan, so it is the cheapest job in the hygiene
workflow and it reports the same thing locally as it does on a runner.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

# Every `gh release edit` invocation, after comments are dropped and shell line
# continuations are joined.
EDIT = re.compile(r"gh release edit[^\n]*")

# A trailing backslash and the indentation of the next line: joined so a flag written on
# the following line still counts as part of the same command.
CONTINUATION = re.compile(r"\\\n\s*")

# The prose beside a command names it too ("`gh release edit` takes no assets"), so comment
# lines are removed before matching rather than filtered out afterwards.
COMMENT = re.compile(r"^\s*#")

# gh needs the `=` form for a false boolean: `--draft false` would be read as a positional
# argument, so this is the exact spelling that publishes.
PUBLISHES = "--draft=false"

# A workflows directory that has gone empty, or a glob that has stopped matching, must fail
# rather than report a clean repository -- the same floor verify-action-pins.py carries.
MIN_WORKFLOW_FILES = 1

# (label, sample, expected commands found, expected failures). A scanner that can only ever
# say "clean" proves nothing, so these run every time: the first two prove it can fail and
# can pass, the third that a continuation is joined, and the last two that it does not match
# prose about the command or a different gh subcommand.
CONTROLS: tuple[tuple[str, str, int, int], ...] = (
    (
        "an edit with no flag is caught",
        '          gh release edit "$TAG" --notes-file body.md\n',
        1,
        1,
    ),
    (
        "an edit that publishes passes",
        '          gh release edit "$TAG" --draft=false --notes-file body.md\n',
        1,
        0,
    ),
    (
        "a flag on the next line counts",
        '          gh release edit "$TAG" \\\n            --draft=false --notes-file body.md\n',
        1,
        0,
    ),
    (
        "prose about the command is not a command",
        "          # `gh release edit` takes no assets, so the upload is explicit\n",
        0,
        0,
    ),
    (
        "another gh subcommand is not this one",
        '          gh release create "$TAG" --notes-file body.md\n',
        0,
        0,
    ),
)


def commands(text: str) -> list[str]:
    """Every `gh release edit` invocation in one workflow's text."""
    code = "\n".join(line for line in text.split("\n") if not COMMENT.match(line))

    return EDIT.findall(CONTINUATION.sub(" ", code))


def failures_in(text: str) -> list[str]:
    """The invocations in one workflow's text that would leave a release as a draft."""
    return [c for c in commands(text) if PUBLISHES not in c]


def broken_controls() -> list[str]:
    """Any control the matcher no longer handles, which makes every result meaningless."""
    out: list[str] = []
    for label, sample, found, bad in CONTROLS:
        got_found, got_bad = len(commands(sample)), len(failures_in(sample))
        if (got_found, got_bad) != (found, bad):
            out.append(
                f"  CONTROL   {label}: expected {found} command(s) and {bad} failure(s), "
                f"got {got_found} and {got_bad}"
            )

    return out


def workflows_dir(argv: list[str]) -> Path:
    """Which `.github/workflows` to check.

    Defaults to the repository this script lives in, which is what a package running its
    own copy wants. An explicit path argument is what makes the script shareable: the
    reusable hygiene workflow checks this repository out beside the caller's and points the
    script at the caller's workflows.

    Deriving the path from `__file__` alone silently scanned the wrong repository the moment
    the script stopped being a local copy.
    """
    if len(argv) > 1:
        return Path(argv[1]).resolve() / ".github" / "workflows"

    return Path(__file__).resolve().parent.parent / ".github" / "workflows"


def main() -> int:
    workflows = workflows_dir(sys.argv)

    print()
    print(f"  Release publishing: {workflows}")
    print()

    broken = broken_controls()
    if broken:
        print("  The matcher is broken, so nothing below can be trusted:")
        print()
        for line in broken:
            print(line)
        print()
        print("  Fix the matcher before reading any result from this script.")
        return 1

    if not workflows.is_dir():
        print(f"  No workflows directory at {workflows}.")
        return 1

    # Both extensions: GitHub reads either, and globbing one silently skipped the other.
    workflow_files = sorted(p for p in workflows.iterdir() if p.suffix in (".yml", ".yaml"))

    if len(workflow_files) < MIN_WORKFLOW_FILES:
        print(
            f"  Refusing to report a clean repository: {len(workflow_files)} workflow "
            f"files in {workflows}, floor is {MIN_WORKFLOW_FILES}."
        )
        return 1

    found = 0
    failures: list[str] = []

    for workflow in workflow_files:
        for command in commands(workflow.read_text()):
            found += 1
            if PUBLISHES in command:
                print(f"  OK        {workflow.name}: {command.strip()}")
            else:
                failures.append(
                    f"  DRAFT     {workflow.name}: {command.strip()}\n"
                    f"            add {PUBLISHES}, or a deleted-and-re-pushed tag leaves "
                    f"this release invisible on a green run"
                )

    print()
    if failures:
        for line in failures:
            print(line)
        print()
        print(f"  {len(failures)} of {found} release edit(s) would leave a draft behind.")
        return 1

    if found:
        print(
            f"  {len(workflow_files)} workflow files, {found} release edit(s): every one "
            f"publishes the release it edits."
        )
    else:
        print(
            f"  {len(workflow_files)} workflow files, no hand-rolled release edit: nothing "
            f"here can leave a draft behind."
        )

    return 0


if __name__ == "__main__":
    sys.exit(main())
