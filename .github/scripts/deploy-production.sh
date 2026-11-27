#!/usr/bin/env bash
set -Eeuo pipefail

log() {
    printf '[DAODES DEPLOY] %s\n' "$1"
}

fail() {
    printf '[DAODES DEPLOY] ERROR: %s\n' "$1" >&2
    exit 1
}

: "${DEPLOY_PATH:?DEPLOY_PATH is required}"

[ -d "$DEPLOY_PATH" ] || fail "Project directory does not exist: $DEPLOY_PATH"
cd "$DEPLOY_PATH"

git rev-parse --is-inside-work-tree >/dev/null 2>&1 || fail "DEPLOY_PATH is not a Git working tree."

branch="$(git branch --show-current)"
[ "$branch" = "main" ] || fail "Expected production checkout on branch main; found: $branch"

if [ -n "$(git status --porcelain)" ]; then
    git status --short
    fail "Working tree is not clean. No files were changed."
fi

[ -f composer.json ] || fail "composer.json is missing."
[ -f composer.lock ] || fail "composer.lock is missing."
[ -f package.json ] || fail "package.json is missing."
[ -f package-lock.json ] || fail "package-lock.json is missing."
[ -f .env ] || fail ".env is missing. Refusing to create or replace production environment."

command -v composer >/dev/null 2>&1 || fail "Composer is not installed or not in PATH."
command -v npm >/dev/null 2>&1 || fail "npm is not installed or not in PATH."
command -v php >/dev/null 2>&1 || fail "PHP is not installed or not in PATH."

previous_commit="$(git rev-parse HEAD)"
log "Current commit: $previous_commit"
log "Fetching origin/main"

git fetch --prune origin main

target_commit="$(git rev-parse origin/main)"
if [ "$previous_commit" = "$target_commit" ]; then
    log "Production already points to origin/main."
else
    git merge --ff-only origin/main || fail "Fast-forward update failed. Production checkout was not reset."
fi

log "Installing production Composer dependencies"
composer install \
    --no-interaction \
    --prefer-dist \
    --no-dev \
    --optimize-autoloader

log "Installing frontend dependencies"
npm ci

log "Building frontend assets"
npm run build

log "Refreshing Laravel caches"
php artisan optimize:clear
php artisan optimize

log "Deployment completed."
log "Previous commit: $previous_commit"
log "Current commit: $(git rev-parse HEAD)"
