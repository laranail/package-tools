#!/usr/bin/env bash
# Delete local branches whose remote branch is gone and whose work is on the default branch.
#
# Usage:
#   prune-merged-branches.sh [CHECKOUT] [--apply]
#
# The local half of "delete a branch after it merges". GitHub deletes the remote branch on
# merge (delete_branch_on_merge, set by apply-rulesets.sh); this removes the local copy once
# `git fetch --prune` shows the remote one gone. A branch is deleted only when its upstream is
# gone AND its tip is contained in origin/<default> (or `git cherry` finds no commit missing
# from it). Kept, with the reason printed: the current branch, the default branch, a branch
# that was never pushed, a branch whose upstream still exists, and a gone branch whose work is
# not on the default branch. Dry run unless --apply. Deleting also removes the branch's
# `branch.<name>` config section.
set -euo pipefail

checkout="."
apply=0
for arg in "$@"; do
  case "$arg" in
    --apply) apply=1 ;;
    -*) sed -n '4,5p' "$0" | sed 's/^# //' >&2; exit 2 ;;
    *) checkout="$arg" ;;
  esac
done

if ! git -C "$checkout" rev-parse --git-dir >/dev/null 2>&1; then
  echo "$checkout: not a git checkout." >&2
  exit 2
fi

git -C "$checkout" fetch --prune --quiet origin

default="$(git -C "$checkout" symbolic-ref --quiet --short refs/remotes/origin/HEAD 2>/dev/null || true)"
default="${default#origin/}"
if [ -z "$default" ]; then
  for candidate in main master; do
    if git -C "$checkout" show-ref --verify --quiet "refs/remotes/origin/${candidate}"; then
      default="$candidate"
      break
    fi
  done
fi
if [ -z "$default" ]; then
  echo "$checkout: cannot tell the default branch (no origin/HEAD, origin/main or origin/master)." >&2
  exit 1
fi

current="$(git -C "$checkout" symbolic-ref --quiet --short HEAD || true)"
base="origin/${default}"
deleted=0
kept=0

while IFS=$'\t' read -r branch upstream track; do
  reason=""
  if [ "$branch" = "$current" ]; then
    reason="current branch"
  elif [ "$branch" = "$default" ]; then
    reason="default branch"
  elif [ -z "$upstream" ]; then
    reason="never pushed (no upstream)"
  elif [ "$track" != "[gone]" ]; then
    reason="upstream ${upstream} still exists"
  elif git -C "$checkout" merge-base --is-ancestor "$branch" "$base"; then
    reason=""
  elif [ -z "$(git -C "$checkout" cherry "$base" "$branch" | grep '^+' || true)" ]; then
    reason=""
  else
    reason="upstream gone, but not merged into ${base}"
  fi

  if [ -n "$reason" ]; then
    printf 'keep    %s  (%s)\n' "$branch" "$reason"
    kept=$((kept + 1))
    continue
  fi

  printf 'delete  %s  (upstream gone, merged into %s)\n' "$branch" "$base"
  deleted=$((deleted + 1))
  if [ "$apply" -eq 1 ]; then
    git -C "$checkout" branch -D --quiet "$branch"
    git -C "$checkout" config --remove-section "branch.${branch}" 2>/dev/null || true
  fi
done < <(git -C "$checkout" for-each-ref --format='%(refname:short)%09%(upstream:short)%09%(upstream:track)' refs/heads)

if [ "$apply" -eq 1 ]; then
  echo "Deleted ${deleted}, kept ${kept}."
else
  echo "Dry run: would delete ${deleted}, keep ${kept}. Re-run with --apply."
fi
