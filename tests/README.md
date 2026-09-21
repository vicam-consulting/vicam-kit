# npm lint installer checks

Install the Composer dependencies, then run the regression checks:

```sh
composer install
php tests/npm-lint.php
```

These checks exercise the installer's npm command, version constraints, script
preservation, failure warning, and missing-package.json behavior using a fake npm
executable. They require PHP and do not contact the npm registry.

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
