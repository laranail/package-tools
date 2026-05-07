#!/usr/bin/env bash
# scripts/init.sh — single bootstrap entrypoint for laranail/package-tools.
# ADR-007: this is the only .sh in the repo.
#
# Behavior (idempotent, exits non-zero on any check failure):
#   1. Verify php >= 8.3 and composer are on PATH.
#   2. composer install (or --no-dev when INIT_PROD=1).
#   3. Discover host Laravel .env if present; never auto-create.
#   4. Smoke-check lint and tests (non-fatal warnings).
#   5. Print summary.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

err()  { printf '\033[31m✗\033[0m %s\n' "$*" >&2; }
ok()   { printf '\033[32m✓\033[0m %s\n'   "$*"; }
info() { printf '\033[34mi\033[0m %s\n'   "$*"; }
warn() { printf '\033[33m!\033[0m %s\n'   "$*"; }

check_only=false
for arg in "$@"; do
    case "$arg" in
        --check-only) check_only=true ;;
    esac
done

# 1. Host checks
command -v php >/dev/null      || { err "php not found on PATH"; exit 2; }
command -v composer >/dev/null || { err "composer not found on PATH"; exit 2; }

php_version=$(php -r 'echo PHP_VERSION;')
php_major_minor=$(printf '%s' "$php_version" | cut -d. -f1-2)
if [ "$(printf '%s\n8.3' "$php_major_minor" | sort -V | head -1)" != "8.3" ]; then
    err "PHP 8.3+ required (found $php_version)"
    exit 2
fi
ok "php $php_version"
ok "composer $(composer --version | sed 's/^Composer version //;s/ .*$//')"

# 2. Composer install
if [ "$check_only" != "true" ]; then
    if [ "${INIT_PROD:-0}" = "1" ]; then
        info "composer install --no-dev"
        composer install --no-dev --no-interaction --prefer-dist
    else
        info "composer install"
        composer install --no-interaction --prefer-dist
    fi
    ok "composer install complete"
fi

# 3. Discover host Laravel .env (walk up from cwd)
env_path=""
dir="$REPO_ROOT"
while [ "$dir" != "/" ]; do
    if [ -f "$dir/.env" ]; then env_path="$dir/.env"; break; fi
    dir="$(dirname "$dir")"
done
if [ -n "$env_path" ]; then
    keys=$(grep -cE '^[A-Z]' "$env_path" 2>/dev/null || echo "0")
    [ -w "$env_path" ] && writable="writable" || writable="read-only"
    ok ".env at $env_path ($keys keys, $writable)"
else
    info "no .env discovered — copy .env.example to .env when ready (we never auto-create)"
fi

# 4. Smoke-check lint + tests (non-fatal)
if [ "$check_only" != "true" ] && [ -f vendor/bin/pint ]; then
    if vendor/bin/pint --test --quiet 2>/dev/null; then
        ok "pint clean"
    else
        warn "pint reports formatting drift (run 'composer pint-fix')"
    fi
fi

# 5. Summary
printf '\n──────────────────────────────────────────\n'
printf '\033[1mlaranail/package-tools\033[0m setup complete\n\n'
printf 'Available composer aliases:\n'
printf '  composer test           — run Pest tests\n'
printf '  composer lint           — pint + phpstan + rector --dry-run\n'
printf '  composer audit          — composer audit (security)\n'
printf '  composer pint-fix       — apply Pint fixes\n'
printf '  composer rector-fix     — apply Rector transformations\n\n'
printf 'Docs: https://laranail.simtabi.com/docs/package-tools/\n'
printf '──────────────────────────────────────────\n'
