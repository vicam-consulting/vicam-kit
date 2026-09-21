# Installer checks

Install the Composer dependencies, then run the regression checks:

```sh
composer install
php tests/npm-lint.php
php tests/installer.php
```

These checks exercise the installer's npm command, version constraints, script
preservation, failure warning, and missing-package.json behavior using a fake npm
executable. The installer checks also cover Composer/npm failures, missing Boost,
Boost failures, provider registration, and successful reruns after failure. They
require PHP and do not contact package registries.

To verify Composer can receive authentication input, run this in a terminal and
enter the literal `test-token` at the simulated prompt (no real credentials):

```sh
php tests/installer.php --tty
```

For actual npm resolution and lint configuration validation, clone a disposable
Laravel Vue starter and run the smoke check (Node.js 22.13+ and npm required):

```sh
git clone https://github.com/laravel/vue-starter-kit.git /path/to/disposable-starter
git -C /path/to/disposable-starter checkout 4bd3e1dee1987339330d699f766d6d805e7ab45e
php tests/npm-lint.php --smoke /path/to/disposable-starter
```

The smoke check modifies that starter's package manifest and lockfile, installs
dependencies with normal npm peer resolution, copies the kit's ESLint config,
and lints and formats a Vue/TypeScript component. The pinned starter uses Laravel
13 and Vite Plus, matching issue #2.

For a complete fresh-install check, use another disposable checkout of that
starter, add this kit as a Composer path repository, then install and rerun:

```sh
cp .env.example .env
composer config repositories.vicam path /absolute/path/to/vicam-kit
composer require 'vicam/vicam-kit:@dev' --no-interaction
php artisan vicam:install --no-interaction
php artisan vicam:install --no-interaction
php artisan list
```

This runs actual Composer/npm resolution, package discovery, data/transformer
configuration, and Boost installation. It requires network access.
