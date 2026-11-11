#!/usr/bin/env bash
# Apply both repository rulesets to any GitHub repository: the default-branch ruleset
# (pull request + passing checks, no force-push or deletion) and the release-tag ruleset
# (v* and src/v* cannot be moved or deleted).
#
# Usage:
#   apply-rulesets.sh OWNER/REPO [--pr NUMBER] [--apply]
#
# Dry run by default. Runs from any directory. Re-running with --apply updates both
# rulesets in place, which is how the required checks are refreshed after a workflow
# is added or renamed. Needs the personal `gh` login (the app token cannot manage
# rulesets). The branch script needs PyYAML; it is installed once into its own
# environment ($APPLY_RULESETS_VENV, default ~/.local/share/py-ruleset), never into the
# system Python.
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
venv="${APPLY_RULESETS_VENV:-$HOME/.local/share/py-ruleset}"

if [ "$#" -lt 1 ] || [[ "$1" != */* ]]; then
  sed -n '6,7p' "$0" | sed 's/^# //' >&2
  exit 2
fi

if ! [ -x "$venv/bin/python" ]; then
  python3 -m venv "$venv"
fi
if ! "$venv/bin/python" -c 'import yaml' 2>/dev/null; then
  "$venv/bin/pip" install --quiet --disable-pip-version-check pyyaml
fi

"$venv/bin/python" "$here/apply-branch-ruleset.py" "$@"

# The tag script takes no --pr; pass only the repository and --apply.
tag_args=("$1")
for arg in "$@"; do
  [ "$arg" = "--apply" ] && tag_args+=("--apply")
done
"$venv/bin/python" "$here/apply-tag-ruleset.py" "${tag_args[@]}"
