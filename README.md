# Vicam Kit

Reusable Laravel + Inertia + Vue starter kit with AI guidelines, components, and utilities.

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

3. Run the Vicam Kit installer:

php artisan vicam:install

4. Answer the installer prompts for the guidelines, components, lint tools, and optional Laravel Data setup the project should use.

5. If this is an intentional reinstall and existing generated files should be overwritten, run:

php artisan vicam:install --force

The installer copies Vicam guidelines, optional Vue/Inertia components, utility files, lint tooling, and related dependencies. It also runs Laravel Boost's boost:install command during installation, so if that command is unavailable, install/configure Laravel Boost and then rerun php artisan vicam:install.
```
