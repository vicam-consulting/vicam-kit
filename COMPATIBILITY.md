# Compatibility policy

Target baseline: Laravel 13.23+ on PHP 8.3–8.5. Laravel 11/12 are no longer claimed. Release validation is still in progress; the matrix below is not a claim that every acceptance check has passed.

| Area | Supported constraint | Notes |
| --- | --- | --- |
| PHP | 8.3, 8.4, 8.5 | Laravel 13 requires PHP 8.3+. |
| Laravel | `^13.23` | CI covers PHP 8.3, 8.4, and 8.5. |
| Inertia Laravel | `^3.3` | Laravel 13-compatible adapter. |
| Inertia Vue/Vite | `^3.7` | Vite plugin supports Vite 7 and 8. |
| Vue | `^3.5.42` | Satisfies the Inertia and Vue Vite plugin peers. |
| Vite | `^8.0` | Requires Node 20.19+ or 22.12+; SSR requires Node 22+. |
| Laravel Vite Plugin | `^3.2` | Requires Vite 8. |
| Vue Vite Plugin | `^6.0.8` | Selected peer range includes Vite 8. |
| Node | 22.12+ | One minimum for client and SSR. CI uses Node 22.13. |
| Tailwind CSS | 4.1 starter baseline | Existing Tailwind versions are preserved; 3.4 is not yet clean-room verified. |
| Laravel Boost | `^2.7` | Optional; source guidance is stored as discoverable `.md` files. |
| Laravel Data | `^4.23` | Installed for every Vicam application. |
| TypeScript Transformer | `^3.3` | Installed in `require-dev`; provider-based v3 configuration. |
| Pest / PHPUnit on PHP 8.3 | `^4.7` / `^12.5` | Pest 5 and PHPUnit 13 do not support PHP 8.3. |
| Pest / PHPUnit on PHP 8.4.1–8.5 | `^5.1` / `^13.3` | PHP 8.4.0 uses the PHP 8.3 test profile. |
| Larastan / Rector | `^3.11` / `^2.6` | Optional lint profile. |
| Spatie Multitenancy | `^4.2` | Installed only for `path` or `subdomain` selection. |
| Laravel Sanctum | `^4.3` | Installed only when API support is selected. |

The constraints live in `src/Support/CompatibilityManifest.php` and are exercised by package and clean-room CI. Dependencies are resolved without `--force`, `--legacy-peer-deps`, or disabled lifecycle scripts.

## Starter baseline

The tagged `laravel/vue-starter-kit` 1.0.2 baseline is unsupported because it contains Laravel 12, Inertia 2, and Vite 6. New applications use the pinned source commit `290aba0dc11900cc1ac8a433229583b644699b48`. The installer checks the installed Laravel and Node versions before changing files and fails with an upgrade instruction for an unsupported baseline.

```bash
git clone https://github.com/laravel/vue-starter-kit.git my-app
cd my-app
git checkout 290aba0dc11900cc1ac8a433229583b644699b48
composer install
cp .env.example .env
php artisan key:generate
```

Then follow the README install prompt. Keep the generated lockfiles. Existing applications must already use Inertia 3 and Vite 8; the installer is not a Laravel/Inertia major-version migration tool.

## Behavior and recovery

- Reuses lockfiles when dependency constraints are unchanged. npm uses `--no-audit --no-fund`; lifecycle scripts stay enabled.
- Dependency failures restore manifests/lockfiles and return failure. Installed dependencies may be partial: fix the reported error and rerun the same command.
- `.ai/guidelines` and `.ai/skills` are source files. Generated agent files, builds, and SQLite data are ignored.
- `--boost` regenerates guidance only. Existing Boost agents, packages, skills, MCP, and custom settings are preserved. For first use, specify `--boost-agents=codex`; configure other integrations through Boost separately.
- SSR uses the same automatic Inertia entry for client/server layouts. Custom manual setup requires a matching server entry. Start production SSR with `php artisan inertia:start-ssr`.
- Tenancy publishes configuration and guidance, not models or isolation. API uses Laravel's Sanctum scaffold; complete the application's token model and migration setup before use.

## Checks

```bash
composer validate --strict
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=2G
vendor/bin/rector process --dry-run
vendor/bin/pest
npm run lint:check
npm run format:check
npm run types
npm run build
php artisan typescript:transform
```

Package tests: `composer test` and `composer lint`. Full fixture: `sh tests/clean-room.sh`. SSR acceptance also requires an HTTP response containing `data-server-rendered="true"`. Audits are separate: `composer security:audit` and `npm run security:audit`.

Verified locally on PHP 8.5.8: package tests, frontend merger regressions, client/SSR builds, and Boost preservation/idempotence. The full clean-room gate currently fails on 12 static-analysis errors in the pinned starter. Do not tag a release until this and the remaining runtime/matrix checks pass.

Compatibility references: [Laravel releases](https://laravel.com/docs/13.x/releases), [Inertia SSR](https://inertiajs.com/docs/v3/advanced/server-side-rendering), [Vite requirements](https://vite.dev/guide/), and [Transformer setup](https://spatie.be/docs/typescript-transformer/v3/laravel/installation-and-setup). Composer/npm constraints were checked against the corresponding package metadata.
