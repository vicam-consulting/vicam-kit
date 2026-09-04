<?php

declare(strict_types=1);

namespace Vicam\VicamKit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vicam\VicamKit\Support\ProviderRegistrar;

final class ProviderRegistrarTest extends TestCase
{
    public function test_registered_development_provider_is_safe_without_dev_dependencies(): void
    {
        $registrar = new ProviderRegistrar;
        $source = $registrar->merge('<?php return [App\\Providers\\AppServiceProvider::class,];');
        self::assertSame($source, $registrar->merge($source));
        $providers = eval(substr($source, 5));
        self::assertSame(['App\\Providers\\AppServiceProvider'], $providers);
    }

    public function test_existing_unconditional_registration_is_guarded(): void
    {
        $source = (new ProviderRegistrar)->merge('<?php return [App\\Providers\\TypeScriptTransformerServiceProvider::class,];');
        self::assertSame([], eval(substr($source, 5)));
    }

    public function test_existing_registration_without_trailing_comma_is_guarded(): void
    {
        $registrar = new ProviderRegistrar;
        $source = $registrar->merge('<?php return [App\\Providers\\TypeScriptTransformerServiceProvider::class];');
        self::assertSame([], eval(substr($source, 5)));
        self::assertSame($source, $registrar->merge($source));
    }

    public function test_formatted_imports_and_aliases_remain_idempotent(): void
    {
        $source = <<<'PHP'
<?php
use App\Providers\TypeScriptTransformerServiceProvider as Transformer;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseTransformer;
return [...(class_exists(BaseTransformer::class) ? [Transformer::class] : [])];
PHP;
        self::assertSame($source, (new ProviderRegistrar)->merge($source));
    }

    public function test_imported_unguarded_provider_is_replaced(): void
    {
        $source = <<<'PHP'
<?php
use App\Providers\TypeScriptTransformerServiceProvider as Transformer;
return [Transformer::class];
PHP;
        $result = (new ProviderRegistrar)->merge($source);
        self::assertStringNotContainsString('return [Transformer::class]', $result);
        self::assertSame([], eval(substr($result, 5)));
    }

    public function test_a_comment_is_not_a_provider_guard(): void
    {
        $source = <<<'PHP'
<?php
// TODO class_exists(Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider::class)
return [App\Providers\TypeScriptTransformerServiceProvider::class];
PHP;
        $result = (new ProviderRegistrar)->merge($source);
        self::assertSame([], eval(substr($result, 5)));
    }
}
