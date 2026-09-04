<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\Config\RectorConfig;
use Rector\Naming\Rector\ClassMethod\RenameParamToMatchTypeRector;
use Rector\Set\ValueObject\SetList;

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
        // Keep Inertia lazy props as explicit closures (avoid first-class callables here)
        ArrowFunctionDelegatingCallToFirstClassCallableRector::class,
        // Skip this rule - conflicts with Pint's class_attributes_separation
        // (Rector adds blank lines between trait uses, Pint removes them)
        NewlineBetweenClassLikeStmtsRector::class,
        // Skip parameter renaming - breaks Laravel route model binding
        // which requires parameter names to match route parameter names
        RenameParamToMatchTypeRector::class,
    ])
    ->withPhpSets(php82: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        naming: true,
        instanceOf: true,
        earlyReturn: true,
    )
    ->withSets([
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
        SetList::DEAD_CODE,
    ])
    ->withImportNames(
        importShortClasses: false,
        removeUnusedImports: true,
    );
