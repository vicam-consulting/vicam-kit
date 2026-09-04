<?php

declare(strict_types=1);

namespace Vicam\VicamKit\Support;

use RuntimeException;

final class CompatibilityManifest
{
    public const int LARAVEL_MAJOR = 13;

    public const string STARTER_COMMIT = '290aba0dc11900cc1ac8a433229583b644699b48';

    /** @return array<string, string> */
    public function composerRequirements(bool $tenancy, bool $api): array
    {
        $requirements = [
            'inertiajs/inertia-laravel' => '^3.3',
            'laravel/wayfinder' => '^0.1.21',
            'spatie/laravel-data' => '^4.23',
        ];

        if ($tenancy) {
            $requirements['spatie/laravel-multitenancy'] = '^4.2';
        }

        if ($api) {
            $requirements['laravel/sanctum'] = '^4.3';
        }

        return $requirements;
    }

    /** @return array<string, string> */
    public function composerDevRequirements(bool $lint, bool $boost, int $phpVersionId = PHP_VERSION_ID): array
    {
        $requirements = [
            'spatie/laravel-typescript-transformer' => '^3.3',
        ];

        if ($lint) {
            $requirements += [
                'laravel/pint' => '^1.27',
                'larastan/larastan' => '^3.11',
                'rector/rector' => '^2.6',
            ];
        }

        if ($boost) {
            $requirements['laravel/boost'] = '^2.7';
        }

        // Pest 5 / PHPUnit 13 require PHP 8.4. Laravel 13 itself still supports PHP 8.3.
        $requirements += $phpVersionId >= 80401
            ? ['pestphp/pest' => '^5.1', 'pestphp/pest-plugin-laravel' => '^5.0', 'phpunit/phpunit' => '^13.3']
            : ['pestphp/pest' => '^4.7', 'pestphp/pest-plugin-laravel' => '^4.1', 'phpunit/phpunit' => '^12.5'];

        return $requirements;
    }

    /** @return array<string, string> */
    public function npmDependencies(): array
    {
        return [
            '@inertiajs/vite' => '^3.7.0',
            '@inertiajs/vue3' => '^3.7.0',
            'laravel-vite-plugin' => '^3.2.0',
            'vue' => '^3.5.42',
        ];
    }

    /** @return array<string, string> */
    public function npmDevDependencies(bool $lint): array
    {
        $requirements = [
            '@vitejs/plugin-vue' => '^6.0.8',
            'typescript' => '^6.0.3',
            'vite' => '^8.0.0',
            'vue-tsc' => '^3.3.11',
        ];

        if ($lint) {
            $requirements += [
                '@eslint/js' => '^9.39.5',
                '@stylistic/eslint-plugin' => '^5.10.0',
                '@vue/eslint-config-typescript' => '^14.9.0',
                'eslint' => '^9.39.5',
                'eslint-config-prettier' => '^10.1.8',
                'eslint-import-resolver-typescript' => '^4.4.5',
                'eslint-plugin-import' => '^2.32.0',
                'eslint-plugin-vue' => '^10.10.0',
                'prettier' => '^3.6.2',
                'prettier-plugin-tailwindcss' => '^0.6.11',
                'typescript-eslint' => '^8.69.0',
            ];
        }

        return $requirements;
    }

    public function assertSupported(string $laravelVersion, ?string $nodeVersion): void
    {
        $major = (int) explode('.', ltrim($laravelVersion, 'v'))[0];

        if ($major !== self::LARAVEL_MAJOR || version_compare(ltrim($laravelVersion, 'v'), '13.23.0', '<')) {
            throw new RuntimeException(sprintf(
                'Unsupported Laravel baseline %s. Vicam Kit 0.6 supports Laravel 13 only (13.23+); upgrade the application before installing.',
                $laravelVersion,
            ));
        }

        if ($nodeVersion !== null && version_compare(ltrim($nodeVersion, 'v'), '22.12.0', '<')) {
            throw new RuntimeException(sprintf(
                'Unsupported Node baseline %s. Vicam Kit requires Node 22.12+ (including SSR).',
                $nodeVersion,
            ));
        }
    }
}
