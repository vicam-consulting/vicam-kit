<?php

namespace Vicam\VicamKit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

class InstallCommand extends Command
{
    protected $signature = 'vicam:install {--force : Overwrite existing files}';

    protected $description = 'Install Vicam Kit guidelines, components, and utilities';

    private Filesystem $files;

    private int $copiedCount = 0;

    private int $skippedCount = 0;

    public function __construct()
    {
        parent::__construct();
        $this->files = new Filesystem;
    }

    public function handle(): int
    {
        $force = $this->option('force');
        $stubsPath = $this->stubsPath();

        $selected = $this->installGuidelines($stubsPath, $force);
        $this->installComponents($stubsPath, $force);

        // Install laravel-data configs if any laravel-data guidelines were selected
        $dataGuidelines = ['laravel-data-core', 'laravel-data-inertia', 'laravel-data-api'];

        if (array_intersect($selected, $dataGuidelines) !== []) {
            $this->installLaravelDataConfigs($stubsPath, $force);
        }

        $this->newLine();
        info("Vicam Kit installed: {$this->copiedCount} files copied, {$this->skippedCount} skipped.");

        $this->newLine();
        info('Running boost:install to generate CLAUDE.md and MCP config...');
        $this->call('boost:install');

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
            'laravel-data-api' => 'laravel-data-api.blade.php',
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
                'multitenancy' => 'Multitenancy (Spatie)',
                'laravel-data-api' => 'Laravel Data - API (Scramble)',
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
            $source = $stubsPath.'/skills/lint-fix/SKILL.md';
            $destination = base_path('.ai/skills/lint-fix/SKILL.md');
            $this->copyFile($source, $destination, $force);
            $this->installLintTools($stubsPath, $force);
        }

        return $selected;
    }

    private function installComponents(string $stubsPath, bool $force): void
    {
        $installComponents = confirm(
            label: 'Install additional components? (form-field, combobox, utilities, composables, etc.)',
            default: true,
        );

        if (! $installComponents) {
            return;
        }

        $frontendPath = $stubsPath.'/frontend';
        $resourcesPath = base_path('resources/js');

        // Copy components, composables, and lib utilities
        $mappings = [
            'components' => $resourcesPath.'/components',
            'composables' => $resourcesPath.'/composables',
            'lib' => $resourcesPath.'/lib',
        ];

        foreach ($mappings as $stubDir => $targetDir) {
            $sourceDir = $frontendPath.'/'.$stubDir;

            if (! $this->files->isDirectory($sourceDir)) {
                continue;
            }

            $files = $this->files->allFiles($sourceDir);

            foreach ($files as $file) {
                $relativePath = $file->getRelativePathname();
                $destination = $targetDir.'/'.$relativePath;

                $this->copyFile($file->getPathname(), $destination, $force);
            }
        }

        // Copy custom UI components
        $uiSourceDir = $frontendPath.'/ui';

        if ($this->files->isDirectory($uiSourceDir)) {
            $uiFiles = $this->files->allFiles($uiSourceDir);

            foreach ($uiFiles as $file) {
                $relativePath = $file->getRelativePathname();
                $destination = $resourcesPath.'/components/ui/'.$relativePath;

                $this->copyFile($file->getPathname(), $destination, $force);
            }
        }

        // Install npm dependencies required by the components
        $this->installComponentDeps();
    }

    private function installComponentDeps(): void
    {
        $packageJsonPath = base_path('package.json');

        if (! $this->files->exists($packageJsonPath)) {
            return;
        }

        $packages = [
            '@vortechron/query-builder-ts',
            '@tanstack/vue-table',
            '@vueuse/core',
            'class-variance-authority',
            'clsx',
            'lucide-vue-next',
            'reka-ui',
            'signature_pad',
            'tailwind-merge',
        ];

        info('  Installing component dependencies...');

        $process = new Process(array_merge(['npm', 'install', '--save'], $packages));
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(120);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            warning('  Could not install component dependencies. You may need to run: npm install '.implode(' ', $packages));
        }
    }

    private function installLintTools(string $stubsPath, bool $force): void
    {
        $this->newLine();
        info('Setting up lint tools for the lint-fix skill...');

        $this->copyLintConfigs($stubsPath, $force);
        $this->addComposerScriptsAndDeps();
        $this->addNpmScriptsAndDeps();
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

        $packages = ['laravel/pint', 'larastan/larastan', 'rector/rector'];
        info('  Installing '.implode(', ', $packages).'...');

        $process = new Process(array_merge(['composer', 'require', '--dev', '--no-interaction'], $packages));
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(120);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            warning('  Could not install PHP lint dependencies. You may need to run: composer require --dev '.implode(' ', $packages));
        }
    }

    private function addNpmScriptsAndDeps(): void
    {
        $packageJsonPath = base_path('package.json');

        if (! $this->files->exists($packageJsonPath)) {
            warning('  package.json not found, skipping npm lint setup.');

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

        $packages = [
            'eslint',
            '@eslint/js',
            '@vue/eslint-config-typescript',
            'eslint-config-prettier',
            'eslint-plugin-vue',
            'typescript-eslint',
            'prettier',
            'prettier-plugin-organize-imports',
            'prettier-plugin-tailwindcss',
            'vue-tsc',
        ];

        info('  Installing npm lint dependencies...');

        $process = new Process(array_merge(['npm', 'install', '--save-dev'], $packages));
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(120);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            warning('  Could not install npm lint dependencies. You may need to run: npm install --save-dev '.implode(' ', $packages));
        }
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

        // Install composer dependencies first (before copying configs that reference their classes)
        $devPackages = ['spatie/laravel-typescript-transformer'];
        $prodPackages = ['spatie/laravel-data'];

        info('  Installing spatie/laravel-data...');

        $process = new Process(array_merge(['composer', 'require', '--no-interaction', '-W'], $prodPackages));
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(120);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            warning('  Could not install spatie/laravel-data. You may need to run: composer require '.implode(' ', $prodPackages));
        }

        info('  Installing spatie/laravel-typescript-transformer...');

        $process = new Process(array_merge(['composer', 'require', '--dev', '--no-interaction', '-W'], $devPackages));
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(120);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            warning('  Could not install spatie/laravel-typescript-transformer. You may need to run: composer require --dev '.implode(' ', $devPackages));
        }

        // Copy config files after packages are installed (configs reference package classes)
        $configsPath = $stubsPath.'/configs';
        $this->copyFile($configsPath.'/data.php', base_path('config/data.php'), $force);
        $this->copyFile($configsPath.'/typescript-transformer.php', base_path('config/typescript-transformer.php'), $force);

        // Copy FlatExportWriter support class
        $this->copyFile(
            $stubsPath.'/support/Typescript/FlatExportWriter.php',
            base_path('app/Support/Typescript/FlatExportWriter.php'),
            $force
        );
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
