<?php

declare(strict_types=1);

namespace Vicam\VicamKit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use Vicam\VicamKit\Support\CompatibilityManifest;
use Vicam\VicamKit\Support\JsonProjectFile;
use Vicam\VicamKit\Support\ProviderRegistrar;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

final class InstallCommand extends Command
{
    protected $signature = 'vicam:install
        {--guidelines=core : Guideline set (core)}
        {--ssr : Configure Inertia server-side rendering}
        {--tenancy=none : Tenancy mode (none, path, subdomain)}
        {--api : Install Laravel Sanctum API support}
        {--lint : Install the Vicam lint configuration and dependencies}
        {--boost : Run Boost generation after installing source guidance}
        {--boost-agents= : Comma-separated Boost agents for first-time guidance generation}
        {--force : Overwrite Vicam-owned files that already exist}
        {--skip-dependencies : Skip dependency resolution when recovering an interrupted install}
        {--timeout=900 : Dependency command timeout in seconds}';

    protected $description = 'Install the Laravel 13 Vicam application conventions';

    private readonly Filesystem $files;

    private readonly CompatibilityManifest $compatibility;

    /** @var array{copied: array<int, string>, skipped: array<int, string>, failed: array<int, string>} */
    private array $summary = ['copied' => [], 'skipped' => [], 'failed' => []];

    public function __construct()
    {
        parent::__construct();
        $this->files = new Filesystem;
        $this->compatibility = new CompatibilityManifest;
    }

    public function handle(): int
    {
        try {
            $selection = $this->selection();
            $this->validateEnvironment($selection);

            if (! $this->option('skip-dependencies')) {
                $this->installDependencies($selection);
            }

            $this->installSourceGuidance($selection);
            $this->installLaravelData();

            if ($selection['lint']) {
                $this->installLintConfiguration();
            }

            if ($selection['ssr']) {
                $this->configureSsr();
            }

            if ($selection['tenancy'] !== 'none') {
                $this->configureTenancy();
            }

            if ($selection['api']) {
                $this->configureApi();
            }

            $this->mergeGitignore();

            if ($selection['lint']) {
                $this->formatManagedPhpFiles($selection['tenancy'] !== 'none');
            }

            $this->transformTypes();

            if ($selection['boost']) {
                $this->generateBoostFiles();
            }
        } catch (Throwable $exception) {
            $this->summary['failed'][] = $exception->getMessage();
            $this->error($exception->getMessage());
            $this->printSummary();

            return self::FAILURE;
        }

        $this->printSummary();

        return self::SUCCESS;
    }

    /** @return array{ssr: bool, tenancy: string, api: bool, lint: bool, boost: bool} */
    private function selection(): array
    {
        if ($this->option('guidelines') !== 'core') {
            throw new RuntimeException('The only supported guideline set is "core". Feature guidance is selected with --ssr, --tenancy, and --api.');
        }

        if (! $this->input->isInteractive()) {
            $tenancy = $this->option('tenancy');

            if (! is_string($tenancy)) {
                throw new RuntimeException('The --tenancy option must be a string.');
            }

            return [
                'ssr' => (bool) $this->option('ssr'),
                'tenancy' => $this->validTenancyMode($tenancy),
                'api' => (bool) $this->option('api'),
                'lint' => (bool) $this->option('lint'),
                'boost' => (bool) $this->option('boost'),
            ];
        }

        $ssr = confirm('Configure Inertia SSR?', false);
        $tenancy = select(
            label: 'Which tenancy guidance and package setup should be installed?',
            options: ['none' => 'None', 'path' => 'Path-based', 'subdomain' => 'Subdomain-based'],
            default: 'none',
        );

        if (! is_string($tenancy)) {
            throw new RuntimeException('The selected tenancy mode must be a string.');
        }

        return [
            'ssr' => $ssr,
            'tenancy' => $this->validTenancyMode($tenancy),
            'api' => confirm('Install Laravel Sanctum API support?', false),
            'lint' => confirm('Install Vicam lint tooling?', true),
            'boost' => confirm('Run Laravel Boost generation after source installation?', true),
        ];
    }

    private function validTenancyMode(string $mode): string
    {
        if (! in_array($mode, ['none', 'path', 'subdomain'], true)) {
            throw new RuntimeException("Invalid tenancy mode '{$mode}'. Expected none, path, or subdomain.");
        }

        return $mode;
    }

    /** @param array{ssr: bool, tenancy: string, api: bool, lint: bool, boost: bool} $selection */
    private function validateEnvironment(array $selection): void
    {
        $this->timeout();
        $nodeVersion = null;

        if (! $this->option('skip-dependencies')) {
            $node = new Process(['node', '--version'], base_path());
            $node->run();
            if (! $node->isSuccessful()) {
                throw new RuntimeException('Node.js is required. Install Node 22.12 or newer before running vicam:install.');
            }
            $nodeVersion = trim($node->getOutput());
        }

        $this->compatibility->assertSupported(app()->version(), $nodeVersion);

        if ($selection['ssr'] && $nodeVersion !== null && version_compare(ltrim($nodeVersion, 'v'), '22.0.0', '<')) {
            throw new RuntimeException("Inertia 3 SSR requires Node 22 or newer; detected {$nodeVersion}.");
        }

        if (! $this->files->exists(base_path('composer.json')) || ! $this->files->exists(base_path('package.json'))) {
            throw new RuntimeException('Vicam Kit requires an existing Laravel 13 + Inertia Vue application with composer.json and package.json.');
        }

        if ($selection['ssr'] && ! $this->files->exists(resource_path('js/app.ts'))) {
            throw new RuntimeException('SSR setup requires the supported TypeScript entry at resources/js/app.ts.');
        }
    }

    /** @param array{ssr: bool, tenancy: string, api: bool, lint: bool, boost: bool} $selection */
    private function installDependencies(array $selection): void
    {
        info('Resolving the Laravel 13 compatibility manifest...');
        $snapshots = [];

        foreach (['composer.json', 'composer.lock', 'package.json', 'package-lock.json'] as $file) {
            $path = base_path($file);
            $snapshots[$path] = $this->files->exists($path) ? $this->files->get($path) : null;
        }

        try {
            $composer = new JsonProjectFile($this->files, base_path('composer.json'));
            $composerBefore = $composer->all();
            $prod = $this->compatibility->composerRequirements($selection['tenancy'] !== 'none', $selection['api']);
            $platform = $composerBefore['config']['platform']['php'] ?? PHP_VERSION;
            if (! is_string($platform)) {
                throw new RuntimeException('Composer config.platform.php must be a supported PHP version string.');
            }
            $dev = $this->compatibility->composerDevRequirements($selection['lint'], $selection['boost'], version_compare($platform, '8.4.1', '>=') ? 80401 : 80300);
            $composer
                ->mergeDependencies('require', 'require-dev', $prod, $dev)
                ->addMissingMap('scripts', $this->composerScripts($selection['ssr'], $selection['lint']))
                ->save();

            $package = new JsonProjectFile($this->files, base_path('package.json'));
            $packageBefore = $package->all();
            $package
                ->mergeDependencies('dependencies', 'devDependencies', $this->compatibility->npmDependencies(), $this->compatibility->npmDevDependencies($selection['lint']))
                ->addMissingMap('scripts', $this->npmScripts($selection['ssr'], $selection['lint']))
                ->removePlatformOptionalDependencies()
                ->save();

            $composerPackages = [...array_keys($prod), ...array_keys($dev)];
            if ($this->files->exists(base_path('composer.lock')) && $this->sameDependencies($composerBefore, $composer->all(), ['require', 'require-dev'])) {
                $this->runRequired(['composer', 'install', '--no-interaction', '--no-progress']);
            } else {
                $packages = $this->files->exists(base_path('composer.lock')) ? $composerPackages : [];
                $this->runRequired(['composer', 'update', ...$packages, '--with-all-dependencies', '--no-interaction', '--no-progress']);
            }
            if (! $this->files->exists(base_path('package-lock.json')) || ! $this->sameDependencies($packageBefore, $package->all(), ['dependencies', 'devDependencies', 'optionalDependencies'])) {
                $this->runRequired(['npm', 'install', '--package-lock-only', '--no-audit', '--no-fund']);
            }
            $this->runRequired(['npm', 'ci', '--no-audit', '--no-fund']);
        } catch (Throwable $exception) {
            foreach ($snapshots as $path => $contents) {
                if ($contents === null) {
                    if ($this->files->exists($path)) {
                        $this->files->delete($path);
                    }
                } else {
                    $this->files->put($path, $contents);
                }
            }

            throw new RuntimeException('Dependency installation failed; project manifests and lockfiles were restored. '.$exception->getMessage(), 0, $exception);
        }
    }

    /** @return array<string, string|array<int, string>> */
    private function composerScripts(bool $ssr, bool $lint): array
    {
        $scripts = ['security:audit' => 'composer audit'];
        if ($lint) {
            $scripts += [
                'lint' => ['vendor/bin/pint', 'vendor/bin/rector process'],
                'test:types' => 'phpstan analyse --memory-limit=2G',
            ];
        }

        if ($ssr) {
            $scripts['ssr:start'] = '@php artisan inertia:start-ssr';
        }

        return $scripts;
    }

    /** @param array<string, mixed> $before
     * @param  array<string, mixed>  $after
     * @param  array<int, string>  $sections
     */
    private function sameDependencies(array $before, array $after, array $sections): bool
    {
        foreach ($sections as $section) {
            if (($before[$section] ?? []) != ($after[$section] ?? [])) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, string> */
    private function npmScripts(bool $ssr, bool $lint): array
    {
        $scripts = ['types' => 'vue-tsc --noEmit', 'security:audit' => 'npm audit'];

        if ($ssr) {
            $scripts += ['build:ssr' => 'vite build --ssr'];
        }

        if ($lint) {
            $scripts += [
                'lint' => 'eslint . --fix',
                'lint:check' => 'eslint .',
                'format' => 'prettier --write resources/',
                'format:check' => 'prettier --check resources/',
            ];
        }

        return $scripts;
    }

    /** @param array{ssr: bool, tenancy: string, api: bool, lint: bool, boost: bool} $selection */
    private function installSourceGuidance(array $selection): void
    {
        $guidelines = [
            'architecture.blade.php' => 'architecture.md',
            'laravel-data-core.blade.php' => 'laravel-data-core.md',
            'laravel-data-inertia.blade.php' => 'laravel-data-inertia.md',
            'laravel/core.blade.php' => 'laravel/core.md',
            'vue-guidelines.blade.php' => 'vue-guidelines.md',
        ];

        if ($selection['ssr']) {
            $guidelines['server-side-rendering.blade.php'] = 'server-side-rendering.md';
        }

        if ($selection['tenancy'] !== 'none') {
            $guidelines['multitenancy-guidelines.blade.php'] = 'multitenancy-guidelines.md';
            $guidelines[$selection['tenancy'] === 'path'
                ? 'multitenancy-path-based.blade.php'
                : 'multitenancy-subdomain.blade.php'] = 'multitenancy-'.$selection['tenancy'].'-based.md';
        }

        if ($selection['api']) {
            $guidelines['laravel-data-api.blade.php'] = 'laravel-data-api.md';
        }

        foreach ($guidelines as $source => $destination) {
            $this->copyFile($this->stubsPath().'/guidelines/'.$source, base_path('.ai/guidelines/'.$destination));
        }

        if ($selection['lint']) {
            $this->copyFile($this->stubsPath().'/skills/lint-fix/SKILL.md', base_path('.ai/skills/lint-fix/SKILL.md'));
        }
    }

    private function installLaravelData(): void
    {
        $this->copyFile($this->stubsPath().'/configs/data.php', config_path('data.php'));
        $this->copyFile($this->stubsPath().'/support/Typescript/FlatExportWriter.php', app_path('Support/Typescript/FlatExportWriter.php'));
        $this->copyFile($this->stubsPath().'/providers/TypeScriptTransformerServiceProvider.stub', app_path('Providers/TypeScriptTransformerServiceProvider.php'));
        $this->registerProvider();

        foreach (['Data/Requests', 'Data/Responses', 'Enums'] as $directory) {
            $this->ensureTrackedDirectory(app_path($directory));
        }
    }

    private function installLintConfiguration(): void
    {
        foreach (['phpstan.neon', 'eslint.config.js', '.prettierrc', '.prettierignore', 'rector.php'] as $file) {
            $this->copyFile($this->stubsPath().'/lint-configs/'.$file, base_path($file));
        }
        $this->runRequired(['node', __DIR__.'/../Support/merge-eslint.mjs', base_path()]);
        $this->appendLines(base_path('.prettierignore'), [
            'resources/js/actions/**', 'resources/js/routes/**', 'resources/js/wayfinder/**',
            'bootstrap/ssr/**', 'resources/js/types/typescript-transformer-manifest.json',
        ]);

    }

    private function configureSsr(): void
    {
        $this->runRequired(['node', __DIR__.'/../Support/configure-frontend.mjs', base_path(), 'ssr']);
        $this->mergeText(resource_path('views/app.blade.php'), static fn (string $contents): string => str_replace(
            ['@inertiaHead', '@inertia'],
            ['<x-inertia::head />', '<x-inertia::app />'],
            $contents,
        ));

        $package = new JsonProjectFile($this->files, base_path('package.json'));
        $scripts = $package->all()['scripts'] ?? [];

        if (is_array($scripts)) {
            $client = $scripts['build:client'] ?? $scripts['build'] ?? 'vite build';
            if (! is_string($client)) {
                throw new RuntimeException('The existing build script must be a string.');
            }
            $package->mergeMap('scripts', [
                'build:client' => $client,
                'build' => 'npm run build:client && npm run build:ssr',
            ])->addMissingMap('scripts', ['build:ssr' => 'vite build --ssr'])->save();
        }
    }

    private function configureTenancy(): void
    {
        if (! $this->files->exists(config_path('multitenancy.php'))) {
            $this->runArtisanRequired(['vendor:publish', '--provider=Spatie\\Multitenancy\\MultitenancyServiceProvider', '--tag=multitenancy-config', '--no-interaction']);
            $this->summary['copied'][] = 'config/multitenancy.php';
        }
    }

    private function configureApi(): void
    {
        if (! $this->files->exists(base_path('routes/api.php'))) {
            $this->runArtisanRequired(['install:api', '--without-migration-prompt', '--no-interaction']);
        }

        $this->ensureTrackedDirectory(app_path('Data/Api/Responses'));
        $this->ensureTrackedDirectory(app_path('Data/Requests/Api'));
    }

    private function transformTypes(): void
    {
        $this->runArtisanRequired(['typescript:transform']);
        if (! $this->files->exists(resource_path('js/types/generated.ts'))) {
            throw new RuntimeException('TypeScript Transformer did not produce resources/js/types/generated.ts. Review the existing provider configuration.');
        }
        if ($this->files->exists(base_path('node_modules/.bin/prettier'))) {
            $this->runRequired([base_path('node_modules/.bin/prettier'), '--write', 'resources/js/types/generated.ts']);
        }
    }

    private function generateBoostFiles(): void
    {
        $agents = $this->option('boost-agents');
        if (! is_string($agents)) {
            throw new RuntimeException('--boost-agents must be a comma-separated string.');
        }
        $this->runRequired([PHP_BINARY, dirname(__DIR__).'/Support/regenerate-boost.php.stub', base_path(), $agents]);
    }

    private function registerProvider(): void
    {
        $path = base_path('bootstrap/providers.php');

        if (! $this->files->exists($path)) {
            throw new RuntimeException('bootstrap/providers.php is required for TypeScript Transformer registration.');
        }

        $this->mergeText($path, (new ProviderRegistrar)->merge(...));
    }

    private function mergeGitignore(): void
    {
        $path = base_path('.gitignore');
        $contents = $this->files->exists($path) ? rtrim($this->files->get($path)) : '';
        $entries = [
            '/bootstrap/ssr', '/public/build', '/resources/js/actions', '/resources/js/routes',
            '/resources/js/wayfinder', '/AGENTS.md', '/CLAUDE.md', '/.agents/', '/.claude/',
            '/.codex/', '/.cursor/', '/.mcp.json', '/boost.json', '/database/*.sqlite',
        ];

        foreach ($entries as $entry) {
            if (! preg_match('/^'.preg_quote($entry, '/').'$/m', $contents)) {
                $contents .= "\n{$entry}";
            }
        }

        $this->files->put($path, ltrim($contents)."\n");
    }

    private function formatManagedPhpFiles(bool $tenancy): void
    {
        $files = [
            'app/Providers/TypeScriptTransformerServiceProvider.php',
            'app/Support/Typescript/FlatExportWriter.php',
            'bootstrap/providers.php',
            'config/data.php',
        ];

        if ($tenancy) {
            $files[] = 'config/multitenancy.php';
        }

        $files = array_values(array_filter($files, fn (string $file): bool => in_array($file, $this->summary['copied'], true) ||
            in_array($file.' (merged)', $this->summary['copied'], true)
        ));
        if ($files !== []) {
            $this->runRequired([base_path('vendor/bin/pint'), ...$files]);
        }
    }

    private function ensureTrackedDirectory(string $directory): void
    {
        $this->files->ensureDirectoryExists($directory);
        $path = $directory.'/.gitkeep';

        if (! $this->files->exists($path)) {
            $this->files->put($path, '');
            $this->summary['copied'][] = $this->relative($path);
        }
    }

    /** @param array<int, string> $entries */
    private function appendLines(string $path, array $entries): void
    {
        $contents = $this->files->exists($path) ? $this->files->get($path) : '';
        $lines = preg_split('/\\R/', $contents) ?: [];
        foreach ($entries as $entry) {
            if (! in_array($entry, $lines, true)) {
                $contents = rtrim($contents)."\n".$entry."\n";
            }
        }
        $this->files->put($path, $contents);
    }

    /** @param callable(string): string $callback */
    private function mergeText(string $path, callable $callback): void
    {
        if (! $this->files->exists($path)) {
            throw new RuntimeException('Required file not found: '.$this->relative($path));
        }

        $before = $this->files->get($path);
        $after = $callback($before);

        if ($after === $before) {
            $this->summary['skipped'][] = $this->relative($path);

            return;
        }

        $this->files->put($path, $after);
        $this->summary['copied'][] = $this->relative($path).' (merged)';
    }

    private function copyFile(string $source, string $destination): void
    {
        if (! $this->files->exists($source)) {
            throw new RuntimeException('Vicam source file is missing: '.$source);
        }

        if ($this->files->exists($destination) && ! $this->option('force')) {
            $this->summary['skipped'][] = $this->relative($destination);

            return;
        }

        $this->files->ensureDirectoryExists(dirname($destination));
        $this->files->copy($source, $destination);
        $this->summary['copied'][] = $this->relative($destination);
    }

    /** @param array<int, string> $command */
    private function runRequired(array $command): void
    {
        $process = new Process($command, base_path());
        $process->setTimeout($this->timeout());
        $process->run(fn (string $type, string $buffer) => $this->output->write($buffer));

        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                'Command failed (exit %s): %s%s%s',
                $process->getExitCode() ?? 'unknown',
                $process->getCommandLine(),
                PHP_EOL,
                trim($process->getErrorOutput() ?: $process->getOutput()),
            ));
        }
    }

    /** @param array<int, string> $arguments */
    private function runArtisanRequired(array $arguments): void
    {
        $this->runRequired([PHP_BINARY, base_path('artisan'), ...$arguments]);
    }

    private function timeout(): int
    {
        $timeout = filter_var($this->option('timeout'), FILTER_VALIDATE_INT);

        if (! is_int($timeout) || $timeout < 30) {
            throw new RuntimeException('--timeout must be an integer of at least 30 seconds.');
        }

        return $timeout;
    }

    private function printSummary(): void
    {
        $this->newLine();
        info(sprintf(
            'Vicam Kit summary: %d copied/merged, %d skipped, %d failed.',
            count($this->summary['copied']),
            count($this->summary['skipped']),
            count($this->summary['failed']),
        ));

        foreach ($this->summary as $kind => $paths) {
            foreach ($paths as $path) {
                $kind === 'failed' ? warning("  Failed: {$path}") : note('  '.ucfirst($kind).": {$path}");
            }
        }
    }

    private function relative(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }

    private function stubsPath(): string
    {
        return dirname(__DIR__, 2).'/stubs';
    }
}
