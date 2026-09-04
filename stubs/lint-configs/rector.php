<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/bootstrap/cache',
        __DIR__.'/database/migrations',
        __DIR__.'/storage',
        __DIR__.'/vendor',
        // Preserve Laravel callback style; it is not a compatibility migration.
        ClosureToArrowFunctionRector::class,
    ])
    ->withPhpSets(php83: true)
    ->withImportNames(
        importShortClasses: false,
        removeUnusedImports: true,
    );
