#!/usr/bin/env bash
#
# BRT Accounting System — production deploy script.
#
# This is the source of truth for the deployment steps. Paste its body into
# Laravel Forge → Site → Apps → "Deploy Script", or run it manually on the
# server from the site root. Forge exposes $FORGE_SITE_PATH, $FORGE_SITE_BRANCH
# and $FORGE_PHP; when running by hand, set them or edit the values inline.
#
# Long-running processes (Reverb WebSockets, the queue worker, the scheduler)
# are NOT started here — they run as Forge Daemons / Scheduler and are restarted
# on their own. See docs/deployment/forge-setup.md.

set -euo pipefail

cd "${FORGE_SITE_PATH:-$(pwd)}"

PHP="${FORGE_PHP:-php}"
BRANCH="${FORGE_SITE_BRANCH:-main}"

echo "→ Pulling latest code ($BRANCH)"
git pull origin "$BRANCH"

echo "→ Installing PHP dependencies (production)"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "→ Building front-end assets"
npm ci
npm run build

echo "→ Caching config, routes, views and events"
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache

echo "→ Ensuring storage symlink exists"
$PHP artisan storage:link || true

echo "→ Restarting queue workers so they pick up the new code"
$PHP artisan queue:restart

# Reload PHP-FPM so opcache serves the freshly cached files. Forge injects the
# FPM socket name; the flock guard prevents overlapping reloads across deploys.
if [ -n "${FORGE_PHP_FPM:-}" ]; then
    echo "→ Reloading PHP-FPM"
    ( flock -w 10 9 || exit 1
        echo 'Reloading PHP FPM...'; sudo -S service "$FORGE_PHP_FPM" reload ) 9>/tmp/fpmlock
fi

echo "✓ Deploy complete"
