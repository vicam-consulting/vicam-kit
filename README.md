# Vicam Kit

Reusable Laravel + Inertia + Vue starter kit with AI guidelines, Laravel Data, and optional tooling.

## New Laravel Project Setup Prompt

Requires Laravel 13.23+, PHP 8.3–8.5, and Node 22.12+. See [compatibility and verification](COMPATIBILITY.md) for the pinned starter and checks.

Copy this prompt into your coding agent from the root of a compatible Laravel project:

```text
Set up Vicam Kit in this Laravel project.

1. Update composer.json to include the Vicam Kit source repository. Preserve any existing repositories entries and merge this entry into the existing array if one already exists:

{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/vicam-consulting/vicam-kit.git"
        }
    ]
}

2. Require the kit from that source:

composer require vicam/vicam-kit:^0.6 --with-all-dependencies

3. Do not run the Vicam Kit installer automatically. Ask me whether I want to run it now, and whether existing generated files should be overwritten.

4. If I confirm that the installer should run, run:

php artisan vicam:install

If I want existing generated files overwritten, run:

php artisan vicam:install --force

Then let me answer the interactive installer prompts. If I do not want the installer run yet, stop after requiring the Composer package and tell me I can run php artisan vicam:install later.

The installer copies Vicam guidance and Laravel Data setup. SSR, tenancy, API, lint tooling, and Boost guidance generation are optional. Custom frontend components are no longer included.
```

## Unattended installation

```bash
php artisan vicam:install --no-interaction --lint --ssr --tenancy=path --boost --boost-agents=codex
```

Omitted features stay off. Use `--api` for Sanctum, `--tenancy=subdomain` for subdomain guidance, `--timeout=1200` for slower installs, and `--skip-dependencies` only to recover an interrupted install. `--guidelines=core` is the only guideline set. Existing files are preserved unless `--force` is set; unrelated configuration is merged.
