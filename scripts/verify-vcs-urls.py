#!/usr/bin/env python3
"""Check that every `laranail/*` VCS url names the repository it actually is.

The family resolves its inter-package dependencies through git VCS repositories
rather than Packagist, so a `repositories` entry is the only thing telling
composer where a sibling package lives. GitHub keeps redirecting the old name
after a repository is renamed, so a stale url keeps working -- and keeps working
right up until somebody creates a new repository under the freed name, at which
point every consumer silently resolves a different project.

That is not hypothetical. `laranail/authkit-social` was renamed to
`laranail/authkit-social-login` while `authkit-preset` went on declaring the old
url. It resolved correctly the whole time, which is exactly why nobody noticed.

Four outcomes, all reported:

  REDIRECT     the url names a repository that has since been renamed
  MISSING      GitHub says no such repository exists
  UNREACHABLE  the API could not be asked, after retries
  SPLIT        a package's composer name no longer matches its own repository

SPLIT misleads a reader rather than a tool. A rename moves the repository and
leaves `composer.json`'s `name` alone, so the two disagree and every later sweep
that maps one onto the other is wrong about that package while being right about
the rest. The authkit-social rename produced precisely that.

MISSING and UNREACHABLE are separated deliberately. The first run of this script
reported `laranail/email` as MISSING on a TLS handshake timeout, for a repository
that is public and fine. A gate that goes red on a network blip is one people
learn to ignore, which is the failure it exists to prevent -- so a transport error
is retried, and if it persists it is reported under its own name.

None of these break a build the day they appear, which is the argument for a
check rather than a convention: the damage is deferred, so review cannot see it
and CI has nothing to fail on.

A SCHEDULED DRIFT DETECTOR, NOT A MERGE GATE, for the same reason
`verify-trait-copies.py` is: in `--org` mode it reads every package's default
branch over the API, so a pull request introducing a stale url is caught on the
next run rather than before it merges.

Sources are `verify-trait-copies.py`'s, imported rather than copied, so the local
and CI runs cannot drift into checking different things.

  verify-vcs-urls.py --org laranail
  verify-vcs-urls.py --from-checkout ~/…/laranail/packages
"""

from __future__ import annotations

import argparse
import importlib.util
import json
import re
import shutil
import subprocess
import sys
import time
from pathlib import Path

ATTEMPTS = 3
BACKOFF_SECONDS = 2


def _sources():
    """Remote, Checkout and Unreachable from verify-trait-copies.py, by path: the
    filename has hyphens, so it is not importable as a module name.

    Bytecode writing is off for the import: otherwise every run leaves a
    `scripts/__pycache__/` behind in the repository this script is checking, which
    `.gitignore` does not cover.
    """
    sys.dont_write_bytecode = True
    path = Path(__file__).with_name("verify-trait-copies.py")
    spec = importlib.util.spec_from_file_location("laranail_trait_copies", path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module.Remote, module.Checkout, module.Unreachable


_canonical: dict[str, tuple[str | None, str | None, bool]] = {}


def canonical_name(org: str, slug: str) -> tuple[str | None, str | None, bool]:
    """The repository's current full_name, following GitHub's rename redirect.

    Returns (full_name, error, absent). `absent` separates "GitHub says this does
    not exist" from "we could not ask".
    """
    key = f"{org}/{slug}"
    if key in _canonical:
        return _canonical[key]

    error = "unknown error"
    for attempt in range(ATTEMPTS):
        result = subprocess.run(
            ["gh", "api", f"repos/{org}/{slug}", "--jq", ".full_name"],
            capture_output=True,
            text=True,
        )
        if result.returncode == 0:
            _canonical[key] = (result.stdout.strip(), None, False)
            return _canonical[key]

        error = (result.stderr.strip().splitlines() or ["unknown error"])[-1]
        if "404" in error or "Not Found" in error:
            _canonical[key] = (None, error, True)
            return _canonical[key]
        if attempt < ATTEMPTS - 1:
            time.sleep(BACKOFF_SECONDS)

    _canonical[key] = (None, error, False)
    return _canonical[key]


def declared_slugs(manifest: dict, org: str) -> list[str]:
    slugs = []
    for entry in manifest.get("repositories") or []:
        if not isinstance(entry, dict):
            continue
        url = str(entry.get("url") or "")
        if f"github.com/{org}/" not in url:
            continue
        slugs.append(re.sub(r"\.git$", "", url.rstrip("/").rsplit("/", 1)[1]))
    return sorted(set(slugs))


def repo_slug_of(pkg: str, root: Path | None, org: str) -> str:
    """The package's real repository slug.

    In a checkout this is read from `origin`, never from the directory name:
    seven directories in the tree are named differently from their repository
    (`pdf-toolkit` is `laranail/pdf`), so trusting the name would report a SPLIT
    that is a local naming choice rather than a rename.
    """
    if root is None:
        return pkg
    url = subprocess.run(
        ["git", "-C", str(root / pkg), "remote", "get-url", "origin"],
        capture_output=True,
        text=True,
    ).stdout.strip()
    if f"github.com/{org}/" in url:
        return re.sub(r"\.git$", "", url.rstrip("/").rsplit("/", 1)[1])
    return pkg


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--org", default="laranail")
    ap.add_argument("--from-checkout", metavar="DIR", default=None)
    args = ap.parse_args()

    if shutil.which("gh") is None:
        print("  gh is not installed; cannot verify vcs urls.")
        return 0

    Remote, Checkout, Unreachable = _sources()
    root = Path(args.from_checkout).expanduser().resolve() if args.from_checkout else None
    src = Checkout(root) if root else Remote(args.org)

    packages = src.packages()
    if not packages:
        print(f"  No packages found via {'checkout' if root else 'the ' + args.org + ' org'}.")
        return 1

    failures: list[str] = []
    urls_checked = 0

    unreadable: list[str] = []

    for pkg in packages:
        # One unreachable manifest must not abort the sweep -- but it must not be
        # mistaken for a clean one either. `verify-trait-copies.py` exits 2 on any
        # unreachable read, for the stated reason that the check "must not degrade
        # into an empty set"; this keeps that guarantee per package, reporting the
        # findings it did see and still refusing to call the run clean.
        try:
            raw = src.read(pkg, "composer.json")
        except Unreachable as e:
            unreadable.append(f"  UNREACHABLE {pkg}: {e}")
            continue
        if raw is None:
            continue
        try:
            manifest = json.loads(raw)
        except json.JSONDecodeError:
            failures.append(f"  UNREADABLE  {pkg}/composer.json is not valid json")
            continue

        for slug in declared_slugs(manifest, args.org):
            urls_checked += 1
            full_name, error, absent = canonical_name(args.org, slug)
            if full_name is None:
                label = "MISSING    " if absent else "UNREACHABLE"
                failures.append(f"  {label} {pkg} -> {args.org}/{slug}: {error}")
                continue
            actual = full_name.split("/", 1)[1]
            if actual != slug:
                failures.append(
                    f"  REDIRECT    {pkg} -> {args.org}/{slug} was renamed to "
                    f"{args.org}/{actual}; update the url"
                )

        name = str(manifest.get("name") or "")
        if name.startswith(f"{args.org}/"):
            slug = repo_slug_of(pkg, root, args.org)
            full_name, _error, _absent = canonical_name(args.org, slug)
            if full_name is not None:
                repo = full_name.split("/", 1)[1]
                if repo != name.split("/", 1)[1]:
                    failures.append(
                        f"  SPLIT       {pkg}: package is {name} but the repository "
                        f"is {args.org}/{repo}"
                    )

    # A run that inspected no urls proves nothing, and would report a clean
    # family while the search was broken.
    if urls_checked == 0:
        print(f"  Inspected {len(packages)} package(s) and found no {args.org}/* vcs urls at all.")
        return 1

    if failures:
        print("\n".join(failures))
    if unreadable:
        print("\n".join(unreadable))

    if failures:
        print(f"  {len(failures)} finding(s) across {len(packages)} packages, {urls_checked} urls.")
        return 1

    # Exit 2, not 0: a run that could not read part of the family has not cleared
    # it. Distinct from 1 so "a url is wrong" and "I could not look" are never
    # read as the same result.
    if unreadable:
        print(f"  No findings, but {len(unreadable)} package(s) could not be read -- this did not clear them.")
        return 2

    print(f"  {urls_checked} {args.org}/* vcs url(s) across {len(packages)} packages all name the repository they are.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
