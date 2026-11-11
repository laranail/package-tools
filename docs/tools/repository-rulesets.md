# Repository rulesets

Two scripts protect any GitHub repository's default branch and its release tags, one command
applies both and turns on branch deletion after merge, and two more prune merged local branches
and reset release tags on purpose.

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
- **Merged branches are deleted by default.** With `--apply` the wrapper also sets the
  repository's `delete_branch_on_merge` to `true`, so GitHub deletes a pull request's branch once
  it merges. Pass `--keep-branches` to set it to `false` instead. A dry run prints the value it
  would set and changes nothing.

Read the result back:

```bash
gh api repos/laranail/settings/rules/branches/main --jq '[.[].type]'
gh api repos/laranail/settings/rulesets --jq '.[] | "\(.id) \(.target) \(.enforcement) \(.name)"'
```

The branch must list `deletion`, `non_fast_forward`, `pull_request` and `required_status_checks`.

## Deleting branches after merge

`delete_branch_on_merge` is a repository setting, not a ruleset, so the wrapper sets it before the
rulesets and it works on a free-plan private repository too. The branch ruleset only lets a pull
request merge once its required checks pass, so on a repository carrying both, a branch is deleted
only after a merge that passed validation and tests. Opt out per repository:

```bash
cd ~/Artisan/projects/opensource && laranail/packages/package-tools/scripts/apply-rulesets.sh laranail/settings --keep-branches --apply
```

GitHub deletes only the remote branch. `scripts/prune-merged-branches.sh` removes the local copy:

```bash
laranail/packages/package-tools/scripts/prune-merged-branches.sh laranail/packages/settings
laranail/packages/package-tools/scripts/prune-merged-branches.sh laranail/packages/settings --apply
```

It runs `git fetch --prune`, then deletes a local branch only when its upstream is gone **and** its
tip is on `origin/<default>` (or `git cherry` finds nothing missing from it), along with its
`branch.<name>` config section. It keeps the current branch, the default branch, a branch that was
never pushed, a branch whose upstream still exists, and a gone branch whose work is not on the
default branch, and prints the reason for each. A squash-merged branch is kept, because its
commits are not on the default branch. Without `--apply` it only prints.

## Private repositories

Rulesets are unavailable on private repositories on the GitHub Free plan: the API answers `403
Upgrade to GitHub Pro` and both scripts exit `3` with a message. Hold the same rules by practice
there.

## Resetting tags on purpose

The tag ruleset blocks every move, including a deliberate one, because a published tag is already
cached. `scripts/reset-release-tags.py` is the one sanctioned way through. Use it only for a planned
history reset or re-tag that the owner explicitly approved, after reading what its dry run says the
change contradicts.

Re-point one tag (and its `src/` alias, if there is one) at a commit:

```bash
cd ~/Artisan/projects/opensource && laranail/packages/package-tools/scripts/reset-release-tags.py laranail/settings --move v0.1.0 --to 588b6b8
```

Replace every `v*` and `src/v*` tag, and their GitHub releases, with one fresh tag:

```bash
cd ~/Artisan/projects/opensource && laranail/packages/package-tools/scripts/reset-release-tags.py laranail/settings --fresh v0.1.0 --to 588b6b8
```

Both are dry runs. The dry run prints every tag it would move or delete with its current and new
SHAs, every release it would delete, the tags it leaves alone (anything that is not `vX.Y.Z` or
`src/vX.Y.Z`, such as `v1` or `latest`), and the cached copies that would then disagree with git:
the Packagist listing (`https://repo.packagist.org/p2/laranail/settings.json` and its HTTP status),
every Composer dist cache, which keeps the old archive until `composer clear-cache`, and the Go
proxy when `src/v*` tags are involved. To carry it out, add `--apply` and the repository, typed
exactly:

```bash
cd ~/Artisan/projects/opensource && laranail/packages/package-tools/scripts/reset-release-tags.py laranail/settings --fresh v0.1.0 --to 588b6b8 --apply --confirm laranail/settings
```

- **The commit must be on the default branch.** It is checked with GitHub's compare endpoint, and
  anything ahead of or diverged from the default branch is refused.
- **The ruleset is bracketed.** "Protect release tags" is set to `disabled` for the work and back
  to `active` in a `finally`, even when a call fails part-way, and its state is printed at the end.
- **`--move` updates the ref in place**, so the tag's GitHub release stays published instead of
  reverting to a draft. `--fresh` deletes the releases, deletes the tags and creates one tag.
- **The release workflow runs again by itself**, because the new tag arrives on GitHub as a push.
- **Only version tags are ever deleted.**
- **The personal `gh` login**, as for the rulesets.

## Inside a Claude Code sandbox

The sandbox cannot reach PyPI, so the wrapper cannot install PyYAML there. Run the scripts from
your own terminal, or see `_scratch-files/shipping-checklist.md` section 7 for a stand-in that
parses YAML through a package's own `symfony/yaml`.

---

[← Docs index](../../README.md#documentation)
