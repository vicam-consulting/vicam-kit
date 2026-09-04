<?php

declare(strict_types=1);

namespace Vicam\VicamKit\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Vicam\VicamKit\Support\JsonProjectFile;

final class JsonProjectFileTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/vicam-kit-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);
    }

    public function test_it_merges_without_moving_or_replacing_unrelated_values(): void
    {
        $path = $this->directory.'/package.json';
        file_put_contents($path, json_encode([
            'scripts' => ['build' => 'custom-build'],
            'dependencies' => ['existing' => '^1.0'],
            'devDependencies' => ['existing-dev' => '^2.0'],
            'custom' => ['preserved' => true],
        ], JSON_THROW_ON_ERROR));

        (new JsonProjectFile(new Filesystem, $path))
            ->mergeMap('dependencies', ['vue' => '^3.5'])
            ->mergeMap('devDependencies', ['vite' => '^8.0'])
            ->addMissingMap('scripts', ['build' => 'vite build', 'types' => 'vue-tsc --noEmit'])
            ->save();

        $result = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('custom-build', $result['scripts']['build']);
        self::assertSame('vue-tsc --noEmit', $result['scripts']['types']);
        self::assertSame('^1.0', $result['dependencies']['existing']);
        self::assertSame('^2.0', $result['devDependencies']['existing-dev']);
        self::assertTrue($result['custom']['preserved']);
    }

    public function test_it_removes_only_platform_binary_overrides(): void
    {
        $path = $this->directory.'/package.json';
        file_put_contents($path, json_encode(['optionalDependencies' => [
            '@rollup/rollup-linux-x64-gnu' => '1.0.0',
            '@tailwindcss/oxide-win32-x64-msvc' => '1.0.0',
            'lightningcss-linux-x64-gnu' => '1.0.0',
            '@laravel/multiplex' => '^0.4',
        ]], JSON_THROW_ON_ERROR));

        (new JsonProjectFile(new Filesystem, $path))->removePlatformOptionalDependencies()->save();
        $result = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(['@laravel/multiplex' => '^0.4'], $result['optionalDependencies']);
    }

    public function test_existing_dependency_ownership_is_preserved_without_duplicates(): void
    {
        $path = $this->directory.'/composer.json';
        file_put_contents($path, json_encode([
            'require' => ['laravel/boost' => '^2.0'],
            'require-dev' => ['inertiajs/inertia-laravel' => '^3.0', 'laravel/boost' => '^2.0'],
        ], JSON_THROW_ON_ERROR));
        $file = new JsonProjectFile(new Filesystem, $path);
        $file->mergeDependencies('require', 'require-dev',
            ['inertiajs/inertia-laravel' => '^3.3'], ['laravel/boost' => '^2.7']);
        self::assertSame([
            'require' => ['laravel/boost' => '^2.7'],
            'require-dev' => ['inertiajs/inertia-laravel' => '^3.3'],
        ], $file->all());
    }
}
