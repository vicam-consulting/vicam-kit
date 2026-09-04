<?php

declare(strict_types=1);

namespace Vicam\VicamKit\Support;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

final class JsonProjectFile
{
    /** @var array<string, mixed> */
    private array $contents;

    public function __construct(private readonly Filesystem $files, private readonly string $path)
    {
        if (! $files->exists($path)) {
            throw new RuntimeException("Required project manifest not found: {$path}");
        }

        $decoded = json_decode($files->get($path), true, flags: JSON_THROW_ON_ERROR);
        $this->contents = is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, string> $values */
    public function mergeMap(string $section, array $values): self
    {
        $current = $this->contents[$section] ?? [];
        $this->contents[$section] = array_replace(is_array($current) ? $current : [], $values);
        ksort($this->contents[$section]);

        return $this;
    }

    /** @param array<string, string> $production
     * @param  array<string, string>  $development
     */
    public function mergeDependencies(string $productionSection, string $developmentSection, array $production, array $development): self
    {
        foreach ([$productionSection => $production, $developmentSection => $development] as $preferredSection => $packages) {
            foreach ($packages as $package => $constraint) {
                // Existing ownership wins, especially when a dev tool is deliberately shipped in production.
                $section = isset($this->contents[$productionSection][$package])
                    ? $productionSection
                    : (isset($this->contents[$developmentSection][$package]) ? $developmentSection : $preferredSection);
                $this->contents[$section][$package] = $constraint;
                $otherSection = $section === $productionSection ? $developmentSection : $productionSection;
                unset($this->contents[$otherSection][$package]);
            }
        }

        return $this;
    }

    /** @param array<string, string|array<int, string>> $values */
    public function addMissingMap(string $section, array $values): self
    {
        $current = $this->contents[$section] ?? [];
        $this->contents[$section] = array_replace($values, is_array($current) ? $current : []);

        return $this;
    }

    public function removePlatformOptionalDependencies(): self
    {
        $optional = $this->contents['optionalDependencies'] ?? null;

        if (! is_array($optional)) {
            return $this;
        }

        foreach (array_keys($optional) as $package) {
            if (preg_match('/^(?:@rollup\/rollup-|@tailwindcss\/oxide-|lightningcss-)/', (string) $package) === 1) {
                unset($optional[$package]);
            }
        }

        if ($optional === []) {
            unset($this->contents['optionalDependencies']);
        } else {
            $this->contents['optionalDependencies'] = $optional;
        }

        return $this;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->contents;
    }

    public function save(): void
    {
        $this->files->put(
            $this->path,
            json_encode($this->contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n",
        );
    }
}
