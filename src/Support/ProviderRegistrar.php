<?php

declare(strict_types=1);

namespace Vicam\VicamKit\Support;

use RuntimeException;

final class ProviderRegistrar
{
    public function merge(string $source): string
    {
        $dependency = 'Spatie\\LaravelTypeScriptTransformer\\TypeScriptTransformerApplicationServiceProvider';
        $dependencyNames = [$dependency, '\\'.$dependency];
        $providerNames = ['App\\Providers\\TypeScriptTransformerServiceProvider', '\\App\\Providers\\TypeScriptTransformerServiceProvider'];
        preg_match_all('/^use\\s+([^;]+);/m', $source, $imports);
        foreach ($imports[1] as $import) {
            $parts = preg_split('/\\s+as\\s+/i', $import) ?: [];
            $qualified = ltrim(trim($parts[0] ?? ''), '\\');
            $alias = trim($parts[1] ?? substr($qualified, (int) strrpos($qualified, '\\') + 1));
            if ($qualified === $dependency) {
                $dependencyNames[] = $alias;
            }
            if ($qualified === 'App\\Providers\\TypeScriptTransformerServiceProvider') {
                $providerNames[] = $alias;
            }
        }
        $dependencyPattern = implode('|', array_map(static fn (string $name): string => preg_quote($name, '~'), $dependencyNames));
        $code = '';
        foreach (token_get_all($source) as $token) {
            $code .= is_array($token) ? (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true) ? '' : $token[1]) : $token;
        }
        if (preg_match('~class_exists\\s*\\(\\s*(?:'.$dependencyPattern.')\\s*::\\s*class\\s*\\)~', $code)) {
            return $source;
        }

        $expression = '...(class_exists('.$dependency.'::class)'
            .' ? [App\\Providers\\TypeScriptTransformerServiceProvider::class] : []),';
        $providerPattern = implode('|', array_map(static fn (string $name): string => preg_quote($name, '~'), $providerNames));
        $pattern = '~(?<![\\w\\\\])(?:'.$providerPattern.')\\s*::\\s*class\\s*(?:,|(?=\\]))~';
        if (preg_match($pattern, $source)) {
            return preg_replace($pattern, $expression, $source, 1) ?? $source;
        }

        if (! preg_match('/return\\s*\\[/', $source)) {
            throw new RuntimeException('Cannot safely reconcile bootstrap/providers.php: expected a returned array.');
        }

        return preg_replace('/(return\\s*\\[)/', '$1'."\n    ".$expression, $source, 1) ?? $source;
    }
}
