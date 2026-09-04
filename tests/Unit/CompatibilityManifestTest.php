<?php

declare(strict_types=1);

namespace Vicam\VicamKit\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vicam\VicamKit\Support\CompatibilityManifest;

final class CompatibilityManifestTest extends TestCase
{
    #[DataProvider('supportedRuntimes')]
    public function test_it_accepts_supported_laravel_and_node_versions(string $laravel, ?string $node): void
    {
        (new CompatibilityManifest)->assertSupported($laravel, $node);
        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{string, string|null}> */
    public static function supportedRuntimes(): array
    {
        return [
            'minimum node 22' => ['13.23.0', 'v22.12.0'],
            'node 22' => ['13.30.1', 'v22.12.0'],
            'unknown node during manifest-only recovery' => ['13.23.0', null],
        ];
    }

    public function test_it_rejects_an_old_laravel_baseline_with_an_actionable_message(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('supports Laravel 13 only');

        (new CompatibilityManifest)->assertSupported('12.52.0', 'v22.12.0');
    }

    public function test_it_uses_php_compatible_test_tooling(): void
    {
        $manifest = new CompatibilityManifest;

        self::assertSame('^4.7', $manifest->composerDevRequirements(true, false, 80300)['pestphp/pest']);
        self::assertSame('^12.5', $manifest->composerDevRequirements(true, false, 80300)['phpunit/phpunit']);
        self::assertSame('^5.1', $manifest->composerDevRequirements(true, false, 80401)['pestphp/pest']);
        self::assertSame('^13.3', $manifest->composerDevRequirements(true, false, 80401)['phpunit/phpunit']);
    }

    public function test_optional_packages_are_only_selected_explicitly(): void
    {
        $manifest = new CompatibilityManifest;

        self::assertArrayNotHasKey('spatie/laravel-multitenancy', $manifest->composerRequirements(false, false));
        self::assertArrayNotHasKey('laravel/sanctum', $manifest->composerRequirements(false, false));
        self::assertArrayHasKey('spatie/laravel-multitenancy', $manifest->composerRequirements(true, false));
        self::assertArrayHasKey('laravel/sanctum', $manifest->composerRequirements(false, true));
    }

    #[DataProvider('unsupportedNodeVersions')]
    public function test_node_boundary_rejections(string $version): void
    {
        $this->expectException(RuntimeException::class);
        (new CompatibilityManifest)->assertSupported('13.23.0', $version);
    }

    /** @return array<int, array{string}> */
    public static function unsupportedNodeVersions(): array
    {
        return [['20.18.0'], ['20.19.0'], ['21.7.0'], ['22.0.0'], ['22.11.0']];
    }
}
