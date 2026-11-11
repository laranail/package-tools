#!/usr/bin/env bash
# Apply both repository rulesets to any GitHub repository: the default-branch ruleset
# (pull request + passing checks, no force-push or deletion) and the release-tag ruleset
# (v* and src/v* cannot be moved or deleted). It also turns on GitHub's
# delete_branch_on_merge, so a branch is deleted once its pull request merges.
#
# Usage:
#   apply-rulesets.sh OWNER/REPO [--pr NUMBER] [--keep-branches] [--apply]
#
# Dry run by default. Runs from any directory. Re-running with --apply updates both
# rulesets in place, which is how the required checks are refreshed after a workflow
# is added or renamed. --keep-branches sets delete_branch_on_merge to false instead.
# A pull request can only merge once its required checks pass, so a branch is deleted
# only after a merge that passed validation and tests. Needs the personal `gh` login
# (the app token cannot manage rulesets). The branch script needs PyYAML; it is
# installed once into its own environment ($APPLY_RULESETS_VENV, default
# ~/.local/share/py-ruleset), never into the system Python.
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
venv="${APPLY_RULESETS_VENV:-$HOME/.local/share/py-ruleset}"

if [ "$#" -lt 1 ] || [[ "$1" != */* ]]; then
  sed -n '7,8p' "$0" | sed 's/^# //' >&2
  exit 2
fi

repo="$1"
apply=0
delete_on_merge=true
branch_args=()
for arg in "$@"; do
  case "$arg" in
    --keep-branches) delete_on_merge=false ;;
    --apply) apply=1; branch_args+=("$arg") ;;
    *) branch_args+=("$arg") ;;
  esac
done

# The repository setting first: it is not a ruleset, so it also works on a free-plan
# private repository, where the ruleset scripts stop with exit 3.
if [ "$apply" -eq 1 ]; then
  gh api -X PATCH "repos/${repo}" -F "delete_branch_on_merge=${delete_on_merge}" --jq '.delete_branch_on_merge' >/dev/null
  echo "${repo}: delete_branch_on_merge=${delete_on_merge}"
else
  echo "${repo}: would set delete_branch_on_merge=${delete_on_merge} (dry run)"
fi

if ! [ -x "$venv/bin/python" ]; then
  python3 -m venv "$venv"
fi
if ! "$venv/bin/python" -c 'import yaml' 2>/dev/null; then
  "$venv/bin/pip" install --quiet --disable-pip-version-check pyyaml
fi

"$venv/bin/python" "$here/apply-branch-ruleset.py" "${branch_args[@]}"

# The tag script takes no --pr; pass only the repository and --apply.
tag_args=("$repo")
[ "$apply" -eq 1 ] && tag_args+=("--apply")
"$venv/bin/python" "$here/apply-tag-ruleset.py" "${tag_args[@]}"
