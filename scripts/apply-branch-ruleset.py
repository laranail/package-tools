#!/usr/bin/env python3
"""Apply the family's default-branch ruleset: pull request, passing checks, no rewrites.

The ruleset this replaces required a pull request and blocked force pushes and
deletion, and nothing else. A pull request with red CI could still be merged, so
"everything on main went through the gate" was true of the review step and not
of the checks, which are the part that actually catches defects.

Required status checks cannot live in a static payload, because a check is named
by its job, and job names differ per repository (`pest / PHP 8.4 ·
prefer-lowest`, `Tests/windows-latest · PHP 8.5`, ...). So the payload is built
from evidence: the check runs of a pull request that already merged green. Only
what has demonstrably run and passed on a pull request is required, which is the
difference between a gate and a merge button that never lights up.

Five things are deliberately left out of the required set, each because
requiring it would block merges forever:

  PATH-FILTERED  a workflow with `paths` / `paths-ignore` on `pull_request`
                 never reports on a PR that touches only the ignored paths, and a
                 required check that never reports is a PR that cannot merge.
                 Listed, so the fix (drop the filter) is visible.
  SKIPPED        a conditional job (CodeQL gated on visibility, a release-only
                 hygiene step) may not run on the next PR either.
  OTHER APPS     only GitHub Actions check runs are required, and each is pinned
                 to the Actions app (integration 15368), so a commit status
                 posted by anything else under the same name cannot satisfy it.
  NON-PR RUNS    schedule and workflow_dispatch jobs (the weekly macOS run) are
                 never on a pull request.
  REMOVED        a workflow that ran on the evidence pull request but is no
                 longer on the default branch.

Path filters are read from the default branch as it is now, not from the
evidence commit, since a filter added later is exactly what would strand a
required check.

Branches need not be up to date before merging (`strict` off): every change here
goes through a pull request, CI tests the merge result, and on a one-maintainer
repository the rebase churn buys nothing. Zero approvals, because a sole
maintainer cannot approve their own pull request; conversation resolution is on.

Dry run by default: prints the payload and what was excluded, changes nothing.
`--apply` creates the ruleset, or updates it in place when one made by this
script (or its predecessor) already exists, so re-running after adding a
workflow is how the required set is refreshed.

Rulesets are unavailable on private repositories on the free plan; the API
answers 403 and this exits 3 without pretending otherwise.

Usage:
    apply-branch-ruleset.py OWNER/REPO [--pr NUMBER] [--apply]
"""

from __future__ import annotations

import base64
import json
import subprocess
import sys

import yaml

NAME = "default branch: pull request and passing checks"
# Rulesets this script replaces in place rather than stacking a second one beside.
KNOWN_NAMES = {NAME, "main: pull requests only"}
GITHUB_ACTIONS_APP = 15368


def gh(*args: str, stdin: str | None = None) -> str:
    result = subprocess.run(
        ["gh", *args], input=stdin, capture_output=True, text=True, check=False
    )
    if result.returncode != 0:
        raise RuntimeError(result.stderr.strip() or f"gh {' '.join(args)} failed")
    return result.stdout


def gh_json(*args: str) -> object:
    return json.loads(gh(*args))


def pr_runs(repo: str, sha: str) -> list[dict]:
    return gh_json("api", f"repos/{repo}/actions/runs?head_sha={sha}&event=pull_request&per_page=100")["workflow_runs"]


def green(runs: list[dict]) -> bool:
    """Ran something, and nothing it ran failed."""
    return bool(runs) and all(r["conclusion"] in ("success", "skipped") for r in runs)


def evidence_pr(repo: str, number: str | None) -> tuple[str, str]:
    """The pull request whose green checks define the required set.

    The newest merged pull request is often a docs-only one on which every
    path-filtered workflow stayed silent, so it can carry no runs at all. Walk
    back through recent merges to the first that actually ran something, and ran it green.
    """
    if number is not None:
        pr = gh_json("pr", "view", number, "--repo", repo, "--json", "headRefOid")
        return number, pr["headRefOid"]

    merged = gh_json("pr", "list", "--repo", repo, "--state", "merged",
                     "--limit", "15", "--json", "number,headRefOid,author")
    for pr in merged:
        # Dependabot pull requests run the same workflows, but prefer a human one when
        # there is a choice: it is the shape the next pull request will have.
        if pr["author"]["login"].startswith("app/"):
            continue
        if green(pr_runs(repo, pr["headRefOid"])):
            return str(pr["number"]), pr["headRefOid"]
    for pr in merged:
        if green(pr_runs(repo, pr["headRefOid"])):
            return str(pr["number"]), pr["headRefOid"]
    raise SystemExit(f"{repo}: none of the last {len(merged)} merged pull requests ran its checks green; pass --pr.")


def pull_request_filter(workflow_yaml: str) -> str | None:
    """The path filter on the workflow's pull_request trigger, if it has one."""
    doc = yaml.safe_load(workflow_yaml) or {}
    # YAML 1.1 reads a bare `on:` key as the boolean True.
    triggers = doc.get("on", doc.get(True))
    if isinstance(triggers, dict):
        pr = triggers.get("pull_request")
        if isinstance(pr, dict):
            for key in ("paths", "paths-ignore"):
                if key in pr:
                    return key
    return None


def current_workflow(repo: str, path: str, branch: str) -> str | None:
    """The workflow as the default branch has it now, or None if it is gone."""
    try:
        content = gh_json("api", f"repos/{repo}/contents/{path}?ref={branch}")
    except RuntimeError as e:
        if "404" in str(e) or "Not Found" in str(e):
            return None
        raise
    return base64.b64decode(content["content"]).decode()


def collect(repo: str, sha: str) -> tuple[list[str], list[str]]:
    required: set[str] = set()
    excluded: list[str] = []
    branch = gh_json("api", f"repos/{repo}")["default_branch"]

    for run in pr_runs(repo, sha):
        path = run["path"].split("@")[0]
        jobs = gh_json("api", run["jobs_url"] + "?per_page=100")

        # Judged against the default branch as it is now, not as it was at the evidence
        # commit: a filter added since would otherwise make a required check that
        # never reports on the pull requests it filters out.
        source = current_workflow(repo, path, branch)
        path_filter = None if source is None else pull_request_filter(source)
        for job in jobs["jobs"]:
            label = f"{run['name']} / {job['name']}"
            if source is None:
                excluded.append(f"REMOVED        {label}  ({path} is no longer on {branch})")
            elif path_filter:
                excluded.append(f"PATH-FILTERED  {label}  ({path} has `{path_filter}`)")
            elif job["conclusion"] == "skipped":
                excluded.append(f"SKIPPED        {label}")
            elif job["conclusion"] != "success":
                raise SystemExit(f"{label} concluded {job['conclusion']}; pick a pull request that merged green.")
            else:
                required.add(job["name"])

    return sorted(required), excluded


def payload(contexts: list[str]) -> dict:
    return {
        "name": NAME,
        "target": "branch",
        "enforcement": "active",
        "conditions": {"ref_name": {"include": ["~DEFAULT_BRANCH"], "exclude": []}},
        "rules": [
            {"type": "deletion"},
            {"type": "non_fast_forward"},
            {"type": "pull_request", "parameters": {
                "required_approving_review_count": 0,
                "dismiss_stale_reviews_on_push": False,
                "require_code_owner_review": False,
                "require_last_push_approval": False,
                "required_review_thread_resolution": True,
            }},
            {"type": "required_status_checks", "parameters": {
                "strict_required_status_checks_policy": False,
                "do_not_enforce_on_create": False,
                "required_status_checks": [
                    {"context": c, "integration_id": GITHUB_ACTIONS_APP} for c in contexts
                ],
            }},
        ],
    }


def main(argv: list[str]) -> int:
    args = argv[1:]
    apply = "--apply" in args
    number = None
    if "--pr" in args:
        number = args[args.index("--pr") + 1]
    positional = [a for a in args if not a.startswith("--") and a != number]
    if len(positional) != 1 or "/" not in positional[0]:
        print(__doc__.split("Usage:")[1].strip(), file=sys.stderr)
        return 2
    repo = positional[0]

    pr, sha = evidence_pr(repo, number)
    contexts, excluded = collect(repo, sha)

    # A required set built from nothing passes everything. Refuse it.
    if not contexts:
        print(f"No passing pull_request check runs on #{pr} ({sha[:7]}); nothing to require.", file=sys.stderr)
        return 1

    print(f"{repo}: {len(contexts)} required check(s) from #{pr} ({sha[:7]})")
    for c in contexts:
        print(f"  require        {c}")
    for e in excluded:
        print(f"  {e}")

    body = json.dumps(payload(contexts))
    if not apply:
        print("\nDry run. Payload:\n" + json.dumps(payload(contexts), indent=2))
        return 0

    try:
        existing = gh_json("api", f"repos/{repo}/rulesets?includes_parents=false")
    except RuntimeError as e:
        if "403" in str(e) or "Upgrade to GitHub Pro" in str(e):
            print(f"{repo}: rulesets unavailable (private repository on the free plan).", file=sys.stderr)
            return 3
        raise

    match = next((r for r in existing if r["name"] in KNOWN_NAMES and r["target"] == "branch"), None)
    if match:
        result = json.loads(gh("api", "-X", "PUT", f"repos/{repo}/rulesets/{match['id']}", "--input", "-", stdin=body))
        print(f"Updated ruleset {result['id']} in place.")
    else:
        result = json.loads(gh("api", "-X", "POST", f"repos/{repo}/rulesets", "--input", "-", stdin=body))
        print(f"Created ruleset {result['id']}.")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
