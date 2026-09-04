<?php

declare(strict_types=1);

namespace Vicam\VicamKit\Tests\Feature;

use Illuminate\Testing\PendingCommand;
use Orchestra\Testbench\TestCase;
use Vicam\VicamKit\VicamKitServiceProvider;

final class InstallCommandTest extends TestCase
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [VicamKitServiceProvider::class];
    }

    public function test_interactive_defaults_are_explicit_and_safe(): void
    {
        $command = $this->artisan('vicam:install', ['--skip-dependencies' => true]);
        self::assertInstanceOf(PendingCommand::class, $command);

        $command
            ->expectsConfirmation('Configure Inertia SSR?', 'no')
            ->expectsChoice(
                'Which tenancy guidance and package setup should be installed?',
                'none',
                ['none' => 'None', 'path' => 'Path-based', 'subdomain' => 'Subdomain-based'],
            )
            ->expectsConfirmation('Install Laravel Sanctum API support?', 'no')
            ->expectsConfirmation('Install Vicam lint tooling?', 'yes')
            ->expectsConfirmation('Run Laravel Boost generation after source installation?', 'yes')
            ->assertFailed();
    }

    public function test_non_interactive_mode_rejects_an_invalid_tenancy_value_without_prompting(): void
    {
        $command = $this->artisan('vicam:install', [
            '--no-interaction' => true,
            '--skip-dependencies' => true,
            '--tenancy' => 'database',
        ]);
        self::assertInstanceOf(PendingCommand::class, $command);

        $command
            ->expectsOutputToContain('Invalid tenancy mode')
            ->assertFailed();
    }
}
