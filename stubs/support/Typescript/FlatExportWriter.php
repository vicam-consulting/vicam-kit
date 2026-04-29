<?php

namespace App\Support\Typescript;

use Spatie\TypeScriptTransformer\Actions\ResolveImportsAndResolvedReferenceMapAction;
use Spatie\TypeScriptTransformer\Collections\TransformedCollection;
use Spatie\TypeScriptTransformer\Data\GlobalNamespaceResolvedReference;
use Spatie\TypeScriptTransformer\Data\ModuleImportResolvedReference;
use Spatie\TypeScriptTransformer\Data\WriteableFile;
use Spatie\TypeScriptTransformer\Data\WritingContext;
use Spatie\TypeScriptTransformer\Transformed\Transformed;
use Spatie\TypeScriptTransformer\Writers\Writer;

/**
 * Writes transformed types into a single flat file with namespaced export names.
 *
 * Example:
 * - App\Data\Responses\UserResponse -> App_Data_Responses_UserResponse
 * - App\Data\Api\Responses\UserResponse -> App_Data_Api_Responses_UserResponse
 *
 * Short aliases are added when they can be exposed safely:
 * - Unique names keep the simple short alias
 * - Collisions keep the shortest namespace on the plain short alias
 * - Other collisions receive the smallest distinguishing namespace prefix
 */
class FlatExportWriter implements Writer
{
    public function __construct(
        protected string $path = 'generated.ts',
        protected bool $generateShortAliases = true,
        protected string $namespaceSeparator = '_',
        protected ResolveImportsAndResolvedReferenceMapAction $resolveImportsAndResolvedReferenceMapAction = new ResolveImportsAndResolvedReferenceMapAction,
    ) {}

    /**
     * @param  array<Transformed>  $transformed
     * @return array<WriteableFile>
     */
    public function output(array $transformed, TransformedCollection $transformedCollection): array
    {
        /** @var list<array{transformed: Transformed, shortName: string, fullName: string, namespace: string}> $declarations */
        $declarations = [];
        /** @var array<string, int> $typeNames */
        $typeNames = [];
        /** @var array<string, array<string, string>> $allTypes */
        $allTypes = [];

        foreach ($transformed as $item) {
            $shortName = $item->getName();

            if ($shortName === null) {
                continue;
            }

            $fullName = $this->getFullTypeName($item);
            $namespace = $this->getNamespace($item);

            $declarations[] = [
                'transformed' => $item,
                'shortName' => $shortName,
                'fullName' => $fullName,
                'namespace' => $namespace,
            ];

            $typeNames[$shortName] = ($typeNames[$shortName] ?? 0) + 1;
            $allTypes[$shortName] ??= [];
            $allTypes[$shortName][$namespace] = $fullName;
        }

        usort(
            $declarations,
            fn (array $left, array $right): int => strcmp($left['fullName'], $right['fullName'])
        );

        [$imports, $resolvedReferenceMap] = $this->resolveImportsAndResolvedReferenceMapAction->execute(
            $this->path,
            $transformed,
            $transformedCollection,
        );

        foreach ($declarations as $declaration) {
            $resolvedReferenceMap[$declaration['transformed']->getReference()->getKey()] = $declaration['fullName'];
        }

        $output = '';
        $writingContext = new WritingContext($resolvedReferenceMap);

        foreach ($imports->getTypeScriptNodes() as $import) {
            $output .= $import->write($writingContext).PHP_EOL;
        }

        if ($output !== '' && $declarations !== []) {
            $output .= PHP_EOL;
        }

        foreach ($declarations as $declaration) {
            $statement = $declaration['transformed']->write($writingContext);
            $statement = $this->replaceDeclaredName(
                $statement,
                $declaration['shortName'],
                $declaration['fullName'],
            );

            $output .= $statement.PHP_EOL;

            $alias = $this->resolveAlias(
                $declaration['shortName'],
                $declaration['fullName'],
                $declaration['namespace'],
                $typeNames,
                $allTypes,
            );

            if ($alias !== null) {
                $generics = $this->extractGenericParams($statement, $declaration['fullName']);
                $output .= sprintf(
                    'export type %s%s = %s%s;',
                    $alias,
                    $generics['decl'],
                    $declaration['fullName'],
                    $generics['args'],
                ).PHP_EOL;
            }

            $output .= PHP_EOL;
        }

        return [new WriteableFile($this->path, rtrim($output).PHP_EOL)];
    }

    public function resolveReference(Transformed $transformed): ModuleImportResolvedReference|GlobalNamespaceResolvedReference
    {
        return new GlobalNamespaceResolvedReference($this->getFullTypeName($transformed));
    }

    /**
     * @param  array<string, int>  $typeNames
     * @param  array<string, array<string, string>>  $allTypes
     */
    protected function resolveAlias(
        string $shortName,
        string $fullName,
        string $namespace,
        array $typeNames,
        array $allTypes,
    ): ?string {
        if (! $this->generateShortAliases || $fullName === $shortName) {
            return null;
        }

        if (($typeNames[$shortName] ?? 0) === 1) {
            return $shortName;
        }

        return $this->generateSmartAlias($shortName, $namespace, $allTypes[$shortName] ?? []);
    }

    /**
     * Extract the generic parameter clause from a declaration so it can be forwarded to its short alias.
     *
     * For `export type Foo<TKey, TValue extends Bar = Baz> = ...`, returns:
     *   - decl: `<TKey, TValue extends Bar = Baz>` (preserved verbatim for the alias declaration)
     *   - args: `<TKey, TValue>` (constraints + defaults stripped, used for the alias RHS reference)
     *
     * @return array{decl: string, args: string}
     */
    protected function extractGenericParams(string $statement, string $fullName): array
    {
        $empty = ['decl' => '', 'args' => ''];

        $pattern = '/^(?:export\s+)?(?:declare\s+)?(?:type|interface)\s+'.preg_quote($fullName, '/').'\s*(<[^=]*?>)\s*(?:=|\{|extends)/s';

        if (preg_match($pattern, $statement, $matches) !== 1) {
            return $empty;
        }

        $decl = trim($matches[1]);
        $inner = substr($decl, 1, -1);

        $names = array_map(
            $this->stripGenericConstraint(...),
            $this->splitGenericParams($inner),
        );

        return ['decl' => $decl, 'args' => '<'.implode(', ', $names).'>'];
    }

    protected function stripGenericConstraint(string $param): string
    {
        $trimmed = trim($param);

        if (preg_match('/^(\S+)/', $trimmed, $matches) === 1) {
            return $matches[1];
        }

        return $trimmed;
    }

    /**
     * Split a generic parameter list by top-level commas, honoring nested `<>`, `()`, `{}`, and `[]`.
     *
     * @return array<int, string>
     */
    protected function splitGenericParams(string $inner): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;

        for ($i = 0, $length = strlen($inner); $i < $length; $i++) {
            $char = $inner[$i];

            if (in_array($char, ['<', '(', '{', '['], true)) {
                $depth++;
            } elseif (in_array($char, ['>', ')', '}', ']'], true)) {
                $depth--;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = $buffer;
                $buffer = '';

                continue;
            }

            $buffer .= $char;
        }

        if ($buffer !== '') {
            $parts[] = $buffer;
        }

        return $parts;
    }

    protected function replaceDeclaredName(string $statement, string $shortName, string $fullName): string
    {
        $updatedStatement = preg_replace(
            '/^((?:export\s+)?(?:declare\s+)?(?:type|interface|enum)\s+)'.preg_quote($shortName, '/').'\b/',
            '${1}'.$fullName,
            $statement,
            1,
        );

        return $updatedStatement ?? $statement;
    }

    /**
     * @param  array<string, string>  $variants
     */
    protected function generateSmartAlias(string $shortName, string $currentNamespace, array $variants): ?string
    {
        if ($variants === []) {
            return null;
        }

        $sortedNamespaces = array_keys($variants);
        usort(
            $sortedNamespaces,
            fn (string $left, string $right): int => strlen($left) <=> strlen($right) ?: strcmp($left, $right)
        );

        $shortestNamespace = $sortedNamespaces[0];

        if ($currentNamespace === $shortestNamespace) {
            return $shortName;
        }

        $currentSegments = $this->namespaceSegments($currentNamespace);
        $otherNamespaces = array_values(array_filter(
            $sortedNamespaces,
            fn (string $namespace): bool => $namespace !== $currentNamespace,
        ));

        $distinguishingSegments = $this->findDistinguishingSegments($currentSegments, $otherNamespaces);

        if ($distinguishingSegments === []) {
            $lastSegment = array_pop($currentSegments);

            return $lastSegment !== null ? $lastSegment.'_'.$shortName : null;
        }

        return array_pop($distinguishingSegments).'_'.$shortName;
    }

    /**
     * @param  array<int, string>  $currentSegments
     * @param  array<int, string>  $otherNamespaces
     * @return array<int, string>
     */
    protected function findDistinguishingSegments(array $currentSegments, array $otherNamespaces): array
    {
        $distinguishing = [];

        for ($index = count($currentSegments) - 1; $index >= 0; $index--) {
            $segment = $currentSegments[$index];
            $existsInAnyOther = false;

            foreach ($otherNamespaces as $otherNamespace) {
                if (in_array($segment, $this->namespaceSegments($otherNamespace), true)) {
                    $existsInAnyOther = true;
                    break;
                }
            }

            if (! $existsInAnyOther) {
                $distinguishing[] = $segment;
                break;
            }
        }

        return $distinguishing;
    }

    protected function getFullTypeName(Transformed $transformed): string
    {
        $shortName = $transformed->getName();

        if ($shortName === null) {
            return 'anonymous';
        }

        $namespace = $this->getNamespace($transformed);

        if ($namespace === '') {
            return $shortName;
        }

        $namespacePart = str_replace('\\', $this->namespaceSeparator, $namespace);

        return $namespacePart.$this->namespaceSeparator.$shortName;
    }

    protected function getNamespace(Transformed $transformed): string
    {
        return implode('\\', $transformed->getLocation());
    }

    /**
     * @return array<int, string>
     */
    protected function namespaceSegments(string $namespace): array
    {
        if ($namespace === '') {
            return [];
        }

        return explode('\\', $namespace);
    }
}
