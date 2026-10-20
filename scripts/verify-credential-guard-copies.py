#!/usr/bin/env python3
#
# Every repository that cannot depend on laranail/package-tools carries a local copy
# of its credential guard (`src/Testing/CredentialLiterals.php` and
# `AssertsNoLiteralCredentials`), in a test named NoLiteralCredentialsTest.php. A copy
# is a port, not a byte-for-byte duplicate, so nothing compares it with the original,
# and it falls behind silently: on 2026-10-20 laranail/notifications' copy still had
# the pre-0.1.10 rules, with no purpose check and none of the false-positive
# exclusions, while three other copies had been ported at 0.1.10.
#
# So a copy declares itself, and this script checks three things:
#
#   1. A COPY MUST DECLARE ITSELF, machine-readably, in its docblock:
#
#          @credential-guard-copy reason=<slug> mirrors=<version>
#
#      A NoLiteralCredentialsTest.php that imports package-tools'
#      AssertsNoLiteralCredentials is the shared guard and needs nothing. Anything else
#      is a copy. A copy with no declaration is an ERROR.
#
#   2. THE REASON MUST BE ONE A MACHINE CAN FALSIFY, against the repo's composer.json:
#
#          php-floor-below-package-tools  -- `require.php` admits a version below
#                                            package-tools' own floor
#          outside-laranail               -- the package is not laranail/*: it stays
#                                            independent of the family
#
#      An unknown slug is an error, and a reason that no longer holds is an error: the
#      repo can then take the shared guard, and should.
#
#   3. THE COPY MUST MIRROR THE CURRENT RULES. `mirrors=` must equal
#      CredentialLiterals::RULES_VERSION, which package-tools bumps whenever it changes a
#      rule. Its fingerprint test, CredentialLiteralsRulesVersionTest, makes that bump
#      impossible to forget.
#
# A run that inspects nothing fails: a source that cannot be read must not pass as
# "no copies found".
#
# Usage:
#   verify-credential-guard-copies.py --org laranail --org simtabi        (CI, API)
#   verify-credential-guard-copies.py --from-checkout DIR [--from-checkout DIR ...]

from __future__ import annotations

import argparse
import importlib.util
import json
import re
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent

# The transport and the two sources are the trait-copy script's: one implementation of
# reading the org, not two that can drift into checking different things.
_spec = importlib.util.spec_from_file_location("trait_copies", HERE / "verify-trait-copies.py")
_tc = importlib.util.module_from_spec(_spec)
assert _spec.loader is not None
_spec.loader.exec_module(_tc)

Remote, Checkout, NotFound, Unreachable, php_floor = (
    _tc.Remote, _tc.Checkout, _tc.NotFound, _tc.Unreachable, _tc.php_floor,
)

KNOWN_REASONS = ("php-floor-below-package-tools", "outside-laranail")
GUARD = re.compile(r"(^|/)tests/.*NoLiteralCredentialsTest\.php$")
SHARED = re.compile(r"use\s+Simtabi\\Laranail\\Package\\Tools\\Testing\\AssertsNoLiteralCredentials\s*;")
DECLARATION = re.compile(r"@credential-guard-copy\s+reason=([\w-]+)\s+mirrors=([\d.]+)")
RULES_VERSION = re.compile(r"RULES_VERSION\s*=\s*'([\d.]+)'")


def rules_version() -> str:
    source = (HERE.parent / "src/Testing/CredentialLiterals.php").read_text(encoding="utf-8")
    m = RULES_VERSION.search(source)
    if not m:
        sys.exit("CredentialLiterals::RULES_VERSION not found; cannot check copies.")
    return m.group(1)


def own_floor() -> tuple[int, ...]:
    floor = php_floor((HERE.parent / "composer.json").read_text(encoding="utf-8"))
    if floor is None:
        sys.exit("package-tools' own require.php could not be parsed.")
    return floor


def check_reason(slug: str, manifest: str | None, floor: tuple[int, ...]) -> str | None:
    """None when the reason holds; otherwise why it does not."""
    if slug == "php-floor-below-package-tools":
        mine = php_floor(manifest)
        if mine is None:
            return "no parseable `require.php`, so the floor cannot be compared"
        if mine >= floor:
            return (f"floor {'.'.join(map(str, mine))} is not below package-tools' "
                    f"{'.'.join(map(str, floor))}: use the shared guard instead")
        return None

    if slug == "outside-laranail":
        try:
            name = json.loads(manifest or "{}").get("name", "")
        except json.JSONDecodeError:
            return "composer.json is not valid JSON, so the package name cannot be read"
        if name.startswith("laranail/"):
            return f"the package is {name}, inside the family: use the shared guard instead"
        return None

    return f"unrecognised reason -- must be one of {', '.join(KNOWN_REASONS)}"


def check(sources: list, version: str, floor: tuple[int, ...]) -> int:
    inspected = shared = copies = 0
    unreadable: list[str] = []
    errors: list[str] = []

    for source in sources:
        label = getattr(source, "org", None) or str(getattr(source, "root", "?"))
        for pkg in source.packages():
            try:
                guards = [p for p in source.paths(pkg) if GUARD.search(p)]
            except Unreachable as e:
                unreadable.append(f"{label}/{pkg}: {e}")
                continue
            inspected += 1

            for path in guards:
                body = source.read(pkg, path) or ""
                where = f"{label}/{pkg}/{path}"
                declared = DECLARATION.search(body)

                if SHARED.search(body):
                    shared += 1
                    if declared:
                        errors.append(f"{where}: uses the shared guard but still declares a copy")
                    continue

                copies += 1
                if not declared:
                    errors.append(f"{where}: a local copy with no `@credential-guard-copy reason=… mirrors=…`")
                    continue

                slug, mirrors = declared.groups()
                problem = check_reason(slug, source.read(pkg, "composer.json"), floor)
                if problem:
                    errors.append(f"{where}: reason={slug}: {problem}")
                if mirrors != version:
                    errors.append(f"{where}: mirrors {mirrors}, but the rules are at {version}; port the changes")
                if not errors or not errors[-1].startswith(where):
                    print(f"  ok    {where} (reason={slug}, mirrors={mirrors})")

    print(f"\nInspected {inspected} repositories: {shared} shared guard(s), {copies} local cop(ies).")
    for u in unreadable:
        print(f"  unreadable  {u}")
    if inspected == 0:
        print("Nothing was inspected, so this proved nothing.")
        return 1
    for e in errors:
        print(f"  ERROR {e}")
    return 1 if errors else 0


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--org", action="append", default=[])
    ap.add_argument("--from-checkout", metavar="DIR", action="append", default=[])
    args = ap.parse_args()

    sources = [Checkout(Path(d)) for d in args.from_checkout] + [Remote(o) for o in args.org]
    if not sources:
        sources = [Remote("laranail")]

    try:
        return check(sources, rules_version(), own_floor())
    except Unreachable as e:
        print(f"Could not read the org: {e}")
        return 1


if __name__ == "__main__":
    sys.exit(main())
