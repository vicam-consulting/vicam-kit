#!/bin/sh
set -eu

kit_root="$(cd "$(dirname "$0")/.." && pwd)"
fixture_root="$(mktemp -d "${kit_root}/../.vicam-clean-room.XXXXXX")"
if [ "${VICAM_KEEP_FIXTURE:-0}" = 1 ]; then
    trap 'printf "Fixture retained: %s\n" "$fixture_root"' EXIT
else
    trap 'rm -rf "$fixture_root"' EXIT
fi

git clone --quiet https://github.com/laravel/vue-starter-kit.git "$fixture_root/app"
git -C "$fixture_root/app" checkout --quiet 290aba0dc11900cc1ac8a433229583b644699b48
cd "$fixture_root/app"
rm -rf vendor node_modules composer.lock package-lock.json
cp .env.example .env
touch database/database.sqlite

composer config repositories.vicam --json "{\"type\":\"path\",\"url\":\"$kit_root\",\"options\":{\"versions\":{\"vicam/vicam-kit\":\"0.6.0\"}}}"
composer require vicam/vicam-kit:^0.6 --with-all-dependencies --no-interaction --no-progress
php artisan package:discover --ansi
php artisan key:generate --no-interaction
php artisan vicam:install --no-interaction --lint --ssr --tenancy=path --api --boost --boost-agents=codex --timeout=1200
php artisan vicam:install --no-interaction --lint --ssr --tenancy=path --api --boost --boost-agents=codex --timeout=1200
TEST_TYPESCRIPT_ROOT="$PWD" node --test "$kit_root/tests/frontend-mergers.test.mjs"
php "$kit_root/tests/boost-preservation.php" "$PWD"

composer validate --strict
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=2G
vendor/bin/rector process --dry-run
php artisan wayfinder:generate --with-form
npm run build
vendor/bin/pest
npm run lint:check
npm run format:check
npm run types
npm run build
php artisan typescript:transform
test -f resources/js/types/generated.ts
test -f .ai/guidelines/architecture.md
test -f .ai/guidelines/server-side-rendering.md
test -f .ai/guidelines/multitenancy-path-based.md
test -f .ai/guidelines/laravel-data-api.md
test -f AGENTS.md
test -f boost.json
git check-ignore AGENTS.md boost.json
