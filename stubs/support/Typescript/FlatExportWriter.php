<?php

namespace App\Support\Typescript;

use Spatie\TypeScriptTransformer\Structures\TransformedType;
use Spatie\TypeScriptTransformer\Structures\TypesCollection;
use Spatie\TypeScriptTransformer\Writers\Writer;

/**
 * A TypeScript writer that exports all types with flat, namespaced names and smart aliases.
 *
 * ## Overview
 * Converts PHP namespaces into flat TypeScript names with intelligent short aliases:
 *
 * ## Full Names (Always Generated)
 * - `App\Data\Responses\UserResponse` -> `App_Data_Responses_UserResponse`
 * - `App\Data\Api\Responses\UserResponse` -> `App_Data_Api_Responses_UserResponse`
 *
 * ## Smart Aliases
 * 1. **Unique Names**: Get simple short aliases
 *    - `App\Data\Responses\AddressResponse` -> `AddressResponse`
 *
 * 2. **Name Collisions**: Use minimal distinguishing prefix
 *    - Shortest namespace path gets the plain short name
 *    - Others get prefixed with their unique segment (from right to left)
 *    - Example with 2 collisions:
 *      - `App\Data\Responses\UserResponse` -> `UserResponse` (shortest path)
 *      - `App\Data\Api\Responses\UserResponse` -> `Api_UserResponse` (Api is unique)
 *
 * 3. **Three-way Collisions**: Each gets its unique segment
 *    - `App\Data\Responses\UserResponse` -> `UserResponse` (shortest)
 *    - `App\Data\Api\Responses\UserResponse` -> `Api_UserResponse`
 *    - `App\Data\Web\Responses\UserResponse` -> `Web_UserResponse`
 *
 * ## Type References
 * All type references within definitions use fully qualified names to avoid ambiguity,
 * with context-aware resolution preferring types from the same namespace.
 */
class FlatExportWriter implements Writer
{
    public function __construct(
        protected bool $generateShortAliases = true,
        protected string $namespaceSeparator = '_',
    ) {}

    /**
     * Format a collection of transformed types into TypeScript exports.
     *
     * @param  TypesCollection  $collection  The collection of types to format
     * @return string The formatted TypeScript output
     */
    public function format(TypesCollection $collection): string
    {
        $output = '';
        $typeNames = [];
        $fullTypes = [];
        $allTypes = []; // Map short names to all their full namespaced variants

        // First pass: collect all types and track name occurrences
        foreach ($collection as $type) {
            if ($type->isInline) {
                continue;
            }

            $shortName = $type->reflection->getShortName();
            $fullName = $this->getFullTypeName($type);
            $namespace = $type->reflection->getNamespaceName();

            $fullTypes[] = [
                'type' => $type,
                'fullName' => $fullName,
                'shortName' => $shortName,
                'namespace' => $namespace,
            ];

            // Track how many times each short name appears
            $typeNames[$shortName] = ($typeNames[$shortName] ?? 0) + 1;

            // Track all variants of each short name by namespace
            if (! isset($allTypes[$shortName])) {
                $allTypes[$shortName] = [];
            }

            $allTypes[$shortName][$namespace] = $fullName;
        }

        // Sort by full name for consistent output
        usort($fullTypes, fn (array $a, array $b): int => strcmp($a['fullName'], $b['fullName']));

        // Second pass: generate exports
        foreach ($fullTypes as $fullType) {
            $type = $fullType['type'];
            $fullName = $fullType['fullName'];
            $shortName = $fullType['shortName'];
            $namespace = $fullType['namespace'];
            $transformed = $type->transformed;

            // Build a context-aware type map for this specific type
            // Prefer types from the same namespace, fall back to any available variant
            $contextualTypeMap = $this->buildContextualTypeMap($allTypes, $namespace);

            // Replace short type names with fully namespaced references
            $transformed = $this->replaceNamespacedReferences($transformed, $contextualTypeMap);

            // Determine type kind and build the export statement
            if (str_starts_with($transformed, 'type ')) {
                // It's already a type declaration, just replace the name
                $output .= 'export '.preg_replace(
                    '/^type\s+'.preg_quote((string) $shortName, '/').'/',
                    'type '.$fullName,
                    $transformed
                ).PHP_EOL;
            } elseif (str_starts_with($transformed, 'interface ')) {
                // It's an interface declaration
                $output .= 'export '.preg_replace(
                    '/^interface\s+'.preg_quote((string) $shortName, '/').'/',
                    'interface '.$fullName,
                    $transformed
                ).PHP_EOL;
            } elseif (str_starts_with($transformed, 'enum ')) {
                // It's an enum declaration
                $output .= 'export '.preg_replace(
                    '/^enum\s+'.preg_quote((string) $shortName, '/').'/',
                    'enum '.$fullName,
                    $transformed
                ).PHP_EOL;
            } else {
                // It's a plain object type without a declaration keyword
                $output .= sprintf('export type %s = %s', $fullName, $transformed).PHP_EOL;
            }

            // Generate short alias based on uniqueness
            if ($this->generateShortAliases && $fullName !== $shortName) {
                if ($typeNames[$shortName] === 1) {
                    // Name is unique, use simple short alias
                    $output .= sprintf('export type %s = %s;', $shortName, $fullName).PHP_EOL;
                } else {
                    // Name collision - generate smart alias with minimal distinguishing prefix
                    $smartAlias = $this->generateSmartAlias($shortName, $namespace, $allTypes[$shortName]);
                    if ($smartAlias !== null) {
                        $output .= sprintf('export type %s = %s;', $smartAlias, $fullName).PHP_EOL;
                    }
                }
            }

            $output .= PHP_EOL;
        }

        return rtrim($output).PHP_EOL;
    }

    /**
     * Indicates whether this writer handles its own symbol replacement.
     *
     * Returns false because we manually handle symbol replacement in the
     * replaceNamespacedReferences method to avoid ambiguous replacements.
     */
    public function replacesSymbolsWithFullyQualifiedIdentifiers(): bool
    {
        return false;
    }

    /**
     * Replace short type names with fully namespaced references.
     *
     * Uses the contextual type map to resolve each short name to its fully
     * qualified variant, preferring types from the same namespace when available.
     * Handles cases like: UserResponse, Array<UserResponse>, { user: UserResponse }.
     *
     * @param  string  $transformed  The TypeScript type definition to process
     * @param  array<string, string>  $typeMap  Map of short names to full namespaced names
     * @return string The processed TypeScript with replaced type references
     */
    protected function replaceNamespacedReferences(string $transformed, array $typeMap): string
    {
        // Sort by length descending to replace longer names first (avoid partial replacements)
        $sortedTypes = $typeMap;
        uksort($sortedTypes, fn ($a, $b): int => strlen($b) - strlen($a));

        foreach ($sortedTypes as $shortName => $fullName) {
            // Match the short name as a standalone type reference (not part of a larger word)
            $transformed = preg_replace(
                '/\b'.preg_quote($shortName, '/').'\b/',
                $fullName,
                (string) $transformed
            );
        }

        return $transformed;
    }

    /**
     * Build a contextual type map that prefers types from the same namespace.
     *
     * For each short name, if there's a variant in the current namespace, use that.
     * Otherwise, use any available variant (preferring the first alphabetically).
     *
     * @param  array<string, array<string, string>>  $allTypes  Map of short names to namespace => fullName
     * @param  string  $currentNamespace  The namespace of the type being processed
     * @return array<string, string> Map of short names to preferred full names
     */
    protected function buildContextualTypeMap(array $allTypes, string $currentNamespace): array
    {
        $typeMap = [];

        foreach ($allTypes as $shortName => $variants) {
            // If there's a variant in the same namespace, prefer it
            if (isset($variants[$currentNamespace])) {
                $typeMap[$shortName] = $variants[$currentNamespace];
            } else {
                // Otherwise, use the first variant alphabetically for consistency
                ksort($variants);
                $typeMap[$shortName] = reset($variants);
            }
        }

        return $typeMap;
    }

    /**
     * Generate a smart alias for colliding type names.
     *
     * When multiple types share the same short name, this finds the minimal
     * distinguishing namespace suffix. The variant with the shortest namespace
     * gets the plain short name, others get a prefix.
     *
     * Example:
     * - App\Data\Api\Responses\UserResponse -> Api_UserResponse
     * - App\Data\Responses\UserResponse -> UserResponse
     *
     * @param  string  $shortName  The short type name (e.g., UserResponse)
     * @param  string  $currentNamespace  The namespace of the current type
     * @param  array<string, string>  $variants  All namespace => fullName variants
     * @return string|null The smart alias, or null if this is the "default" variant
     */
    protected function generateSmartAlias(string $shortName, string $currentNamespace, array $variants): ?string
    {
        // Sort namespaces by length - shortest gets the plain name
        $sortedNamespaces = array_keys($variants);
        usort($sortedNamespaces, fn ($a, $b): int => strlen($a) - strlen($b));

        $shortestNamespace = $sortedNamespaces[0];

        // If this is the shortest namespace, it gets the plain short name
        if ($currentNamespace === $shortestNamespace) {
            return $shortName;
        }

        // Find the minimal distinguishing segments
        $currentSegments = explode('\\', $currentNamespace);
        $otherNamespaces = array_filter($sortedNamespaces, fn (string $ns): bool => $ns !== $currentNamespace);

        // Find the last (rightmost) segment that makes this namespace unique
        $distinguishingSegments = $this->findDistinguishingSegments($currentSegments, $otherNamespaces);

        if ($distinguishingSegments === []) {
            // Fallback: use the last segment before the class name
            $lastSegment = end($currentSegments);

            return $lastSegment !== false ? $lastSegment.'_'.$shortName : null;
        }

        // Use the last distinguishing segment for the most concise alias
        $prefix = end($distinguishingSegments);

        return $prefix.'_'.$shortName;
    }

    /**
     * Find the distinguishing segments for a namespace.
     *
     * Returns segments that make this namespace unique compared to others,
     * working from right to left (closest to the type name).
     *
     * @param  array<int, string>  $currentSegments  Current namespace segments
     * @param  array<int, string>  $otherNamespaces  Other namespaces to compare against
     * @return array<int, string> Distinguishing segments
     */
    protected function findDistinguishingSegments(array $currentSegments, array $otherNamespaces): array
    {
        $distinguishing = [];

        // Work backwards through segments (right to left, closest to class name)
        for ($i = count($currentSegments) - 1; $i >= 0; $i--) {
            $segment = $currentSegments[$i];

            // Check if this segment exists in current but not in any other namespace
            $existsInAnyOther = false;
            foreach ($otherNamespaces as $otherNamespace) {
                $otherSegments = explode('\\', $otherNamespace);

                // Check if this segment exists anywhere in the other namespace
                if (in_array($segment, $otherSegments, true)) {
                    $existsInAnyOther = true;
                    break;
                }
            }

            // This segment is unique to current namespace
            if (! $existsInAnyOther) {
                $distinguishing[] = $segment;
                // Return after finding the first (rightmost) unique segment
                break;
            }
        }

        return $distinguishing;
    }

    /**
     * Generate the full type name with namespace prefix.
     *
     * Converts PHP namespace separators (\) to the configured separator (default: _).
     * Example: App\Data\Responses\UserResponse -> App_Data_Responses_UserResponse
     *
     * @param  TransformedType  $transformedType  The type to generate a full name for
     * @return string The full namespaced type name
     */
    protected function getFullTypeName(TransformedType $transformedType): string
    {
        $namespace = $transformedType->reflection->getNamespaceName();
        $shortName = $transformedType->reflection->getShortName();

        if (empty($namespace)) {
            return $shortName;
        }

        // Convert namespace separators to underscores
        $namespacePart = str_replace('\\', $this->namespaceSeparator, $namespace);

        return $namespacePart.$this->namespaceSeparator.$shortName;
    }
}
