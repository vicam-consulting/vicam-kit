<?php

namespace Vicam\VicamKit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

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

        $this->installGuidelines($stubsPath, $force);
        $this->installComponents($stubsPath, $force);

        $this->newLine();
        info("Vicam Kit installed: {$this->copiedCount} files copied, {$this->skippedCount} skipped.");

        $this->newLine();
        info('Running boost:install to generate CLAUDE.md and MCP config...');
        $this->call('boost:install');

        return self::SUCCESS;
    }

    private function installGuidelines(string $stubsPath, bool $force): void
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
        }
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
