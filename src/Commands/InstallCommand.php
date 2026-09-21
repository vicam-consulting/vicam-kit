<?php

namespace Vicam\VicamKit\Commands;

use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Laravel\Boost\BoostServiceProvider;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

class InstallCommand extends Command
{
    protected $signature = 'vicam:install {--force : Overwrite existing files}';

    protected $description = 'Install Vicam Kit guidelines and tooling';

    private Filesystem $files;

    private int $copiedCount = 0;

    private int $skippedCount = 0;

    /** @var list<string> */
    private array $failedSteps = [];

    public function __construct()
    {
        parent::__construct();
        $this->files = new Filesystem;
    }

    public function handle(): int
    {
        $this->failedSteps = [];
        $this->copiedCount = 0;
        $this->skippedCount = 0;
        $force = $this->option('force');
        $stubsPath = $this->stubsPath();

        $selected = $this->installGuidelines($stubsPath, $force);

        // Install laravel-data configs if any laravel-data guidelines were selected
        $dataGuidelines = ['laravel-data-core', 'laravel-data-inertia', 'laravel-data-api'];

        if ($this->failedSteps === [] && array_intersect($selected, $dataGuidelines) !== []) {
            $this->installLaravelDataConfigs($stubsPath, $force);
        }

        if ($this->failedSteps !== []) {
            warning('Vicam Kit setup is incomplete: '.implode(', ', $this->failedSteps).'. Resolve the error above and rerun php artisan vicam:install.');

            return self::FAILURE;
        }

        $this->newLine();
        if (! $this->getApplication()?->has('boost:install')) {
            if (class_exists(BoostServiceProvider::class)) {
                warning('Vicam Kit setup is incomplete: Laravel Boost is installed but disabled. Run setup in your local development environment with Boost enabled.');

                return self::FAILURE;
            }

            if (! $this->installDependencies(['composer', 'require', '--dev', 'laravel/boost'], 'Laravel Boost')) {
                return self::FAILURE;
            }

            // Newly installed commands are discovered in a fresh application process.
            if (! $this->installDependencies([PHP_BINARY, 'artisan', 'boost:install'], 'Laravel Boost setup')) {
                return self::FAILURE;
            }
        } elseif ($this->call('boost:install') !== self::SUCCESS) {
            warning('Vicam Kit setup is incomplete: boost:install failed. Resolve its error and rerun php artisan vicam:install.');

            return self::FAILURE;
        }

        info("Vicam Kit installed: {$this->copiedCount} files copied, {$this->skippedCount} skipped.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string> The selected guideline keys
     */
    private function installGuidelines(string $stubsPath, bool $force): array
    {
        $coreGuidelines = [
            'architecture' => 'architecture.blade.php',
            'laravel-data-core' => 'laravel-data-core.blade.php',
            'laravel-data-inertia' => 'laravel-data-inertia.blade.php',
            'vue-guidelines' => 'vue-guidelines.blade.php',
            'laravel-core-overrides' => 'laravel/core.blade.php',
        ];

        $optionalGuidelines = [
            'multitenancy' => 'multitenancy-guidelines.blade.php',
            'multitenancy-path-based' => 'multitenancy-path-based.blade.php',
            'laravel-data-api' => 'laravel-data-api.blade.php',
            'server-side-rendering' => 'server-side-rendering.blade.php',
        ];

        $allGuidelines = array_merge($coreGuidelines, $optionalGuidelines);

        $selected = multiselect(
            label: 'Which guidelines do you want to install?',
            options: [
                'architecture' => 'Architecture (Actions, DTOs, thin controllers)',
                'laravel-data-core' => 'Laravel Data - Core',
                'laravel-data-inertia' => 'Laravel Data - Inertia',
                'vue-guidelines' => 'Vue Guidelines',
                'laravel-core-overrides' => 'Laravel Core Overrides',
                'multitenancy' => 'Multitenancy (Spatie - Subdomain)',
                'multitenancy-path-based' => 'Multitenancy (Spatie - Path-Based)',
                'laravel-data-api' => 'Laravel Data - API (Scramble)',
                'server-side-rendering' => 'Server-Side Rendering (Inertia SSR)',
            ],
            default: array_keys($coreGuidelines),
        );

        $guidelinesPath = base_path('.ai/guidelines');

        foreach ($selected as $key) {
            $file = $allGuidelines[$key];
            $source = $stubsPath.'/guidelines/'.$file;
            $destination = $guidelinesPath.'/'.$file;

            $this->copyFile($source, $destination, $force);
        }

        // Ask about lint-fix skill
        $installSkill = confirm(
            label: 'Install the lint-fix skill?',
            default: true,
        );

        if ($installSkill) {
            $this->installLintTools($stubsPath, $force);

            if ($this->failedSteps !== []) {
                return $selected;
            }

            $source = $stubsPath.'/skills/lint-fix/SKILL.md';
            $destination = base_path('.ai/skills/lint-fix/SKILL.md');
            $this->copyFile($source, $destination, $force);
        }

        return $selected;
    }

    private function installLintTools(string $stubsPath, bool $force): void
    {
        $this->newLine();
        info('Setting up lint tools for the lint-fix skill...');

        $this->addComposerScriptsAndDeps();
        if ($this->failedSteps !== []) {
            return;
        }

        $this->addNpmScriptsAndDeps();
        if ($this->failedSteps !== []) {
            return;
        }

        $this->copyLintConfigs($stubsPath, $force);
    }

    private function copyLintConfigs(string $stubsPath, bool $force): void
    {
        $lintConfigsPath = $stubsPath.'/lint-configs';

        $configFiles = [
            'phpstan.neon' => base_path('phpstan.neon'),
            'eslint.config.js' => base_path('eslint.config.js'),
            '.prettierrc' => base_path('.prettierrc'),
            '.prettierignore' => base_path('.prettierignore'),
            'rector.php' => base_path('rector.php'),
        ];

        foreach ($configFiles as $source => $destination) {
            $this->copyFile($lintConfigsPath.'/'.$source, $destination, $force);
        }
    }

    private function addComposerScriptsAndDeps(): void
    {
        if (! $this->installDependencies(
            ['composer', 'require', '--dev', 'laravel/pint', 'larastan/larastan', 'rector/rector'],
            'PHP lint dependencies',
        )) {
            return;
        }

        $composerJsonPath = base_path('composer.json');
        $composerJson = json_decode($this->files->get($composerJsonPath), true);

        $scriptsToAdd = [
            'lint' => [
                'vendor/bin/pint',
                'vendor/bin/rector process',
            ],
            'test:types' => 'phpstan analyse --memory-limit=2G',
        ];

        $addedScripts = [];

        foreach ($scriptsToAdd as $name => $command) {
            if (! isset($composerJson['scripts'][$name])) {
                $composerJson['scripts'][$name] = $command;
                $addedScripts[] = $name;
            }
        }

        if (! empty($addedScripts)) {
            $this->files->put(
                $composerJsonPath,
                json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
            );

            foreach ($addedScripts as $script) {
                note("  Added composer script: {$script}");
            }
        }

    }

    private function addNpmScriptsAndDeps(): void
    {
        $packageJsonPath = base_path('package.json');

        if (! $this->files->exists($packageJsonPath)) {
            warning('  package.json not found, skipping npm lint setup.');

            return;
        }

        $packages = [
            // Keep ESLint and its JS config on 9 while eslint-plugin-import requires it.
            'eslint@^9.39.5',
            '@eslint/js@^9.39.5',
            '@stylistic/eslint-plugin@^5.10.0',
            '@vue/eslint-config-typescript@^14.9.0',
            'eslint-config-prettier@^10.1.8',
            'eslint-import-resolver-typescript@^4.4.5',
            'eslint-plugin-import@^2.32.0',
            'eslint-plugin-vue@^10.11.0',
            'typescript-eslint@^8.70.0',
            'prettier@^3.9.8',
            'prettier-plugin-tailwindcss@^0.8.1',
            'vue-tsc@^3.3.11',
        ];

        if (! $this->installDependencies(array_merge(['npm', 'install', '--save-dev'], $packages), 'npm lint dependencies')) {
            return;
        }

        $packageJson = json_decode($this->files->get($packageJsonPath), true);

        $scriptsToAdd = [
            'lint' => 'eslint . --fix',
            'format' => 'prettier --write resources/',
            'lint:types' => 'vue-tsc --noEmit',
        ];

        $addedScripts = [];

        foreach ($scriptsToAdd as $name => $command) {
            if (! isset($packageJson['scripts'][$name])) {
                $packageJson['scripts'][$name] = $command;
                $addedScripts[] = $name;
            }
        }

        if (! empty($addedScripts)) {
            $this->files->put(
                $packageJsonPath,
                json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
            );

            foreach ($addedScripts as $script) {
                note("  Added npm script: {$script}");
            }
        }

    }

    /** @param list<string> $arguments */
    private function dependencyProcess(array $arguments): Process
    {
        $interactive = isset($this->input) && $this->input->isInteractive() && Process::isTtySupported();

        if ($arguments[0] === 'composer') {
            $arguments[] = '--prefer-dist';
            if (! $interactive) {
                $arguments[] = '--no-interaction';
            }
        } elseif (($arguments[1] ?? null) === 'artisan' && ! $interactive) {
            $arguments[] = '--no-interaction';
        }

        $process = new Process($arguments, base_path());
        // Composer may need user input or several minutes for downloads and scripts.
        $process->setTimeout($interactive ? null : 600);
        if ($interactive) {
            $process->setTty(true);
        }

        return $process;
    }

    /** @param list<string> $arguments */
    private function installDependencies(array $arguments, string $label): bool
    {
        if ($arguments[0] === 'composer' && $arguments[1] === 'require') {
            $manifest = json_decode($this->files->get(base_path('composer.json')), true);
            $packages = array_filter(array_slice($arguments, 2), fn (string $argument) => ! str_starts_with($argument, '-'));
            $missing = array_filter($packages, fn (string $package) => (! isset($manifest['require'][$package]) && ! isset($manifest['require-dev'][$package]))
                || ! InstalledVersions::isInstalled($package)
            );

            if ($missing === []) {
                note('  '.$label.' already installed.');

                return true;
            }
        }

        info('  Installing '.$label.'...');
        $process = $this->dependencyProcess($arguments);

        try {
            $process->run(function ($type, $buffer) {
                $this->output->write($buffer);
            });

            if ($process->isSuccessful()) {
                return true;
            }
        } catch (ProcessException $exception) {
            warning('  '.$exception->getMessage());
        }

        $this->failedSteps[] = $label;
        warning('  Could not install '.$label.'. Setup is incomplete. Run: '.implode(' ', $arguments));
        if ($arguments[0] === 'composer') {
            warning('  If Composer reports a GitHub authentication error, run the command above in your project terminal so Composer can request credentials. CI must provide Composer authentication in its environment.');
        }

        return false;
    }

    private function installLaravelDataConfigs(string $stubsPath, bool $force): void
    {
        $installDataConfigs = confirm(
            label: 'Install laravel-data & typescript-transformer config files?',
            default: true,
        );

        if (! $installDataConfigs) {
            return;
        }

        $this->newLine();
        info('Setting up laravel-data configuration...');

        if (! $this->installDependencies(['composer', 'require', '-W', 'spatie/laravel-data'], 'laravel-data')) {
            return;
        }

        if (! $this->installDependencies(['composer', 'require', '--dev', '-W', 'spatie/laravel-typescript-transformer'], 'TypeScript transformer')) {
            return;
        }

        // Copy config files after packages are installed (configs reference package classes)
        $configsPath = $stubsPath.'/configs';
        $this->copyFile($configsPath.'/data.php', base_path('config/data.php'), $force);

        // Copy FlatExportWriter support class
        $this->copyFile(
            $stubsPath.'/support/Typescript/FlatExportWriter.php',
            base_path('app/Support/Typescript/FlatExportWriter.php'),
            $force
        );

        // typescript-transformer v3 reads its config from a user-defined ServiceProvider
        // extending TypeScriptTransformerApplicationServiceProvider — there is no config file.
        // Copy our provider stub, register it, and scaffold the directories it scans.
        $this->copyFile(
            $stubsPath.'/providers/TypeScriptTransformerServiceProvider.stub',
            base_path('app/Providers/TypeScriptTransformerServiceProvider.php'),
            $force
        );

        $this->registerTypeScriptTransformerProvider();
        $this->scaffoldDataAndEnumsDirectories();
    }

    private function registerTypeScriptTransformerProvider(): void
    {
        $providersPath = base_path('bootstrap/providers.php');

        if (! $this->files->exists($providersPath)) {
            warning('  bootstrap/providers.php not found — register App\Providers\TypeScriptTransformerServiceProvider manually.');

            return;
        }

        $contents = $this->files->get($providersPath);

        if (str_contains($contents, 'TypeScriptTransformerServiceProvider')) {
            return;
        }

        $updated = preg_replace(
            '/(return\s*\[\s*\n)((?:\s*[^\n]+\n)*?)(\s*\];)/',
            "$1$2    App\\Providers\\TypeScriptTransformerServiceProvider::class,\n$3",
            $contents,
            1,
        );

        if ($updated === null || $updated === $contents) {
            warning('  Could not auto-register TypeScriptTransformerServiceProvider in bootstrap/providers.php — add it manually.');

            return;
        }

        $this->files->put($providersPath, $updated);
        note('  Registered App\Providers\TypeScriptTransformerServiceProvider in bootstrap/providers.php');
    }

    private function scaffoldDataAndEnumsDirectories(): void
    {
        foreach ([app_path('Data'), app_path('Enums')] as $directory) {
            $this->files->ensureDirectoryExists($directory);

            $gitkeep = $directory.'/.gitkeep';

            if (! $this->files->exists($gitkeep)) {
                $this->files->put($gitkeep, '');
                $relative = str_replace(base_path().'/', '', $gitkeep);
                note("  Created: {$relative}");
            }
        }
    }

    private function copyFile(string $source, string $destination, bool $force): void
    {
        if (! $this->files->exists($source)) {
            return;
        }

        if ($this->files->exists($destination) && ! $force) {
            $relativePath = str_replace(base_path().'/', '', $destination);
            warning("  Skipped: {$relativePath} (already exists, use --force to overwrite)");
            $this->skippedCount++;

            return;
        }

        $this->files->ensureDirectoryExists(dirname($destination));
        $this->files->copy($source, $destination);

        $relativePath = str_replace(base_path().'/', '', $destination);
        note("  Copied: {$relativePath}");
        $this->copiedCount++;
    }

    private function stubsPath(): string
    {
        return dirname(__DIR__, 2).'/stubs';
    }
}
