# Repository rulesets

Two scripts protect any GitHub repository's default branch and its release tags, and one command
applies both.

## The two rulesets

| Ruleset | Script | What it enforces |
|---|---|---|
| `default branch: pull request and passing checks` | `scripts/apply-branch-ruleset.py` | A pull request with zero required approvals and resolved conversations; every check that ran green on a merged pull request; no force-push; no deletion. |
| `Protect release tags` | `scripts/apply-tag-ruleset.py` | No update and no deletion of `v*` and `src/v*` tags. Creating a tag stays allowed. |

Zero approvals, because a sole maintainer cannot approve their own pull request. Required checks
come from evidence, the check runs of a pull request that already merged green, so only jobs that
run on every pull request are required: weekly, dispatch-only, path-filtered and conditional jobs
are listed and left out, because a required check that never reports blocks every merge.

## Apply or update both

```bash
cd ~/Artisan/projects/opensource && laranail/packages/package-tools/scripts/apply-rulesets.sh laranail/settings
```

```bash
cd ~/Artisan/projects/opensource && laranail/packages/package-tools/scripts/apply-rulesets.sh laranail/settings --apply
```

Replace `laranail/settings` with the repository's own `owner/repo`. Do not type angle-bracket
placeholders: zsh reads `<owner>` as a redirect and stops with `parse error near '\n'`.

- **Any repository.** The repository is the only argument, and it works for any owner, not only
  laranail.
- **Dry run first.** Without `--apply` both scripts print their payload and change nothing.
- **Updates in place.** Each ruleset is found by name and replaced, so re-running after a
  workflow is added or renamed is how the required checks are refreshed. `--pr <number>` picks
  the evidence pull request for the branch ruleset.
- **Its own Python environment.** The branch script needs PyYAML, which the wrapper installs once
  into `~/.local/share/py-ruleset` (override with `APPLY_RULESETS_VENV`), never into the system
  Python.
- **The personal `gh` login.** The GitHub App token cannot manage rulesets.

Read the result back:

```bash
gh api repos/laranail/settings/rules/branches/main --jq '[.[].type]'
gh api repos/laranail/settings/rulesets --jq '.[] | "\(.id) \(.target) \(.enforcement) \(.name)"'
```

The branch must list `deletion`, `non_fast_forward`, `pull_request` and `required_status_checks`.

## Private repositories

Rulesets are unavailable on private repositories on the GitHub Free plan: the API answers `403
Upgrade to GitHub Pro` and both scripts exit `3` with a message. Hold the same rules by practice
there.

## Moving a tag on purpose

The tag ruleset blocks every move, including a deliberate one. Disable it, move the tag, then run
the wrapper again with `--apply` to restore it. A move is the owner's decision, after a checklist
naming every cached copy of the old tag it will contradict.

## Inside a Claude Code sandbox

The sandbox cannot reach PyPI, so the wrapper cannot install PyYAML there. Run the scripts from
your own terminal, or see `_scratch-files/shipping-checklist.md` section 7 for a stand-in that
parses YAML through a package's own `symfony/yaml`.

---

[← Docs index](../../README.md#documentation)
