#!/usr/bin/env bash
#
# Release tags are immutable. A public `vX.Y.Z` tag is cached for good by
# Packagist, Go's proxy and npm the first time anything fetches it, so a tag is
# never moved, deleted or reused: the next fix gets the next number. This check
# follows that model, and nothing it prints suggests moving a tag.
#
# What it reports, and how loudly:
#
#   1. REACHABILITY — every tag must be an ancestor of the default branch.
#      ERROR. Composer's VCS driver reads tags, not reachability. This package
#      advertised v0.2.0, v0.2.1 and v0.3.0 pointing at abandoned history, so
#      anyone writing `^0.2` got code that had been discarded months earlier,
#      with nothing anywhere saying so.
#
#   2. THE LIVE LINE — `extra.branch-alias` declares which line is live
#      (`0.1.x-dev` here, `0.7.x-dev` in db-tools). A branch-alias line with no
#      tag at all means `^0.1` resolves nothing. ERROR.
#
#      Anchored on the branch-alias, NOT on the highest tag: laranail/enumerator
#      carried v0.1.0 through v0.4.0, and all nine consumers on `^0.1` resolved
#      v0.1.0 while a check of the highest tag called everything healthy.
#
#   3. RELEASE DUE — the live line's newest tag, or the highest tag overall,
#      trails the default branch by commits that ship code. WARNING, exit 0.
#      Unreleased commits on main are the normal state between releases, not a
#      defect: turning the default branch red for it held back branch cleanup
#      and taught people to ignore red. The warning names the next patch to cut
#      and is also written to the job summary.
#
#      Commits that touch only .github/ ship nothing (the directory is
#      export-ignored), so they never make a release due.
#
# Runs against the remote rather than the local checkout, so a stale local tag
# cannot make it pass, and so it needs no particular fetch depth in CI.

set -euo pipefail

REPO="${1:-}"
BRANCH="${2:-main}"

if [ -z "${REPO}" ]; then
  echo "usage: $(basename "$0") <owner/repo> [branch]" >&2
  exit 2
fi

fail() { printf '\033[31m  x %s\033[0m\n' "$1"; FAILED=1; }
# A release that is due is not a defect: annotate it and summarise it, but never fail on it.
due()  {
  printf '\033[33m  ! %s\033[0m\n' "$1"
  printf '::warning title=Release due::%s\n' "$1"
  if [ -n "${GITHUB_STEP_SUMMARY:-}" ]; then
    printf -- '- **Release due** for `%s`: %s\n' "${REPO}" "$1" >> "${GITHUB_STEP_SUMMARY}"
  fi
}
next_patch() { printf '%s.%s' "${1%.*}" "$(( ${1##*.} + 1 ))"; }
ok()   { printf '\033[32m  ok %s\033[0m\n' "$1"; }
FAILED=0

echo "  Release currency for ${REPO}"
echo

# An empty repository answers 409 to every ref query. Nothing is published, so
# nothing can be behind; treating that as a failure reports a defect where there
# is not even code yet.
if ! gh api "repos/${REPO}/git/refs/heads" >/dev/null 2>&1; then
  ok "No commits yet, so there is nothing to be behind."
  exit 0
fi

head=$(gh api "repos/${REPO}/git/ref/heads/${BRANCH}" --jq '.object.sha')

# Annotated tags point at a tag object; dereference to the commit it wraps.
tag_commit() {
  local ref obj type
  ref=$(gh api "repos/${REPO}/git/ref/tags/$1" 2>/dev/null) || return 1
  obj=$(printf '%s' "${ref}" | jq -r '.object.sha')
  type=$(printf '%s' "${ref}" | jq -r '.object.type')

  if [ "${type}" = "tag" ]; then
    gh api "repos/${REPO}/git/tags/${obj}" --jq '.object.sha'
  else
    printf '%s\n' "${obj}"
  fi
}

# A commit that touches only .github/ ships nothing (the directory is export-ignored), so it is not
# unreleased code: a tag behind main by such commits still hands consumers exactly what main would.
# Dependabot adds one weekly, so without this every package's scheduled check would announce a
# release on a workflow bump.
ci_only() {
  local files
  files=$(gh api "repos/${REPO}/compare/$1...${head}" --jq '.files[].filename' 2>/dev/null) || return 1
  [ -n "${files}" ] || return 1
  ! printf '%s\n' "${files}" | grep -qvE '^\.github/'
}

# Not mapfile: macOS ships bash 3.2, which does not have it, and this has to run
# on a maintainer's laptop as well as in CI.
tags=$(gh api "repos/${REPO}/git/matching-refs/tags/v" --jq '.[].ref | sub("refs/tags/"; "")' 2>/dev/null | sort -V)

if [ -z "${tags}" ]; then
  ok "No v* tag, so nothing is pinned."
  exit 0
fi

# ---------------------------------------------------------------------------
# 1. Reachability
# ---------------------------------------------------------------------------
echo "  Tags"

for tag in ${tags}; do
  commit=$(tag_commit "${tag}") || { fail "${tag} could not be resolved."; continue; }

  # compare returns "identical", "ahead", "behind" or "diverged". Anything but
  # behind-or-identical means the tag is not on this branch's history.
  status=$(gh api "repos/${REPO}/compare/${commit}...${head}" --jq '.status' 2>/dev/null || echo 'unknown')

  case "${status}" in
    identical|ahead) printf '    %-10s %s  on %s\n' "${tag}" "${commit:0:12}" "${BRANCH}" ;;
    *)               fail "${tag} (${commit:0:12}) is not an ancestor of ${BRANCH} — it points at abandoned history, and Composer will still offer it." ;;
  esac
done
echo

# ---------------------------------------------------------------------------
# 2. Currency, on the line this package declares as live
# ---------------------------------------------------------------------------
line=$(gh api "repos/${REPO}/contents/composer.json" --jq '.content' 2>/dev/null \
        | base64 --decode \
        | jq -r '.extra["branch-alias"]["dev-'"${BRANCH}"'"] // empty' \
        | sed 's/\.x-dev$//')

if [ -z "${line}" ]; then
  ok "No branch-alias for dev-${BRANCH}, so no line is declared live."
  [ "${FAILED}" -eq 0 ] && exit 0 || exit 1
fi

current=$(printf '%s\n' "${tags}" | { grep -E "^v${line}\." || true; } | sort -V | tail -1)

echo "  Live line: ${line}.x (from extra.branch-alias)"

if [ -z "${current}" ]; then
  fail "branch-alias declares ${line}.x-dev but no v${line}.* tag exists, so \`^${line}\` resolves nothing."
  exit 1
fi

commit=$(tag_commit "${current}")

echo "    ${current} -> ${commit:0:12}"
echo "    ${BRANCH}  -> ${head:0:12}"
echo

if [ "${commit}" = "${head}" ]; then
  ok "${current} is on ${BRANCH}; consumers on ^${line} resolve current code."

  # ---------------------------------------------------------------------
  # 3. The highest tag overall, for consumers who named no constraint
  # ---------------------------------------------------------------------
  #
  # `composer require laranail/enumerator` with no constraint resolves the
  # highest stable tag, which is not necessarily the branch-alias line. This
  # package had v0.4.0 sitting two commits behind main while v0.1.0 was current,
  # so a check anchored only on branch-alias called it healthy and said nothing
  # about the tag an unconstrained require would actually install.
  #
  # The original version of this script checked only the highest tag and missed
  # the branch-alias line. Replacing one with the other traded one blind spot
  # for another; both are checked.
  highest=$(printf '%s\n' "${tags}" | sort -V | tail -1)

  if [ "${highest}" != "${current}" ]; then
    hcommit=$(tag_commit "${highest}")

    if [ "${hcommit}" = "${head}" ]; then
      ok "${highest} is also on ${BRANCH}."
    else
      hbehind=$(gh api "repos/${REPO}/compare/${hcommit}...${head}" --jq '.ahead_by' 2>/dev/null || echo '?')
      if ci_only "${hcommit}"; then
        ok "${highest} is behind ${BRANCH} only by ${hbehind} commit(s) that touch only .github/."
      else
        due "release due: cut $(next_patch "${highest}"). ${highest} is the highest tag, which an unconstrained \`composer require ${REPO}\` resolves, and ${hbehind} commit(s) on ${BRANCH} are unreleased."
      fi
    fi
  fi
else
  behind=$(gh api "repos/${REPO}/compare/${commit}...${head}" --jq '.ahead_by' 2>/dev/null || echo '?')

  printf '  Commits on %s not in %s: %s\n' "${BRANCH}" "${current}" "${behind}"
  gh api "repos/${REPO}/compare/${commit}...${head}" \
    --jq '.commits[] | "    " + .sha[0:8] + "  " + (.commit.message | split("\n")[0])' 2>/dev/null || true
  echo

  if ci_only "${commit}"; then
    ok "${current} is behind ${BRANCH} only by commits that touch only .github/, so nothing a consumer installs is unreleased."
  else
    due "release due: cut $(next_patch "${current}"); ${behind} commit(s) on ${BRANCH} are unreleased since ${current}."
  fi
fi

exit "${FAILED}"
