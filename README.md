# Vicam Kit

Reusable Laravel + Inertia + Vue starter kit with AI guidelines and tooling.

This release requires Laravel 13 and PHP 8.3+ (including PHP 8.5). Composer selects an earlier compatible kit release for older Laravel applications.

## New Laravel Project Setup Prompt

Copy this prompt into your coding agent from the root of a new Laravel project:

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

composer require vicam/vicam-kit

3. Do not run the Vicam Kit installer automatically. Ask me whether I want to run it now, and whether existing generated files should be overwritten.

4. If I confirm that the installer should run, run:

php artisan vicam:install

If I want existing generated files overwritten, run:

php artisan vicam:install --force

Then let me answer the interactive installer prompts. If I do not want the installer run yet, stop after requiring the Composer package and tell me I can run php artisan vicam:install later.

The installer copies Vicam guidelines, lint tooling, and related dependencies. It also runs Laravel Boost's boost:install command during installation, so if that command is unavailable, install/configure Laravel Boost and then rerun php artisan vicam:install.
```
