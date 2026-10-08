<?php

namespace App\Providers;

use DateTimeInterface;
use Spatie\LaravelTypeScriptTransformer\LaravelTypeScriptTransformerExtension;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider;
use Spatie\TypeScriptTransformer\Formatters\PrettierFormatter;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptString;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;
use Support\TypeScript\AttributedDataClassTransformer;
use Support\TypeScript\AttributedEnumTransformer;

/**
 * Configures `php artisan typescript:transform`, which generates
 * resources/types/generated.d.ts from PHP classes marked #[TypeScript].
 *
 * Types are written as global `declare namespace App.… / Domain.…` blocks, so
 * Vue code references them without imports (e.g.
 * `Domain.Agent.Data.Resources.Account.AgentResourceData`). Re-run the command
 * after changing any #[TypeScript] class and commit the regenerated file.
 */
class TypeScriptTransformerServiceProvider extends TypeScriptTransformerApplicationServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            ->extension(new LaravelTypeScriptTransformerExtension)
            ->transformer(new AttributedDataClassTransformer)
            ->transformer(AttributedClassTransformer::class)
            ->transformer(new AttributedEnumTransformer)
            ->replaceType(DateTimeInterface::class, new TypeScriptString)
            ->transformDirectories(base_path('src/App'), base_path('src/Domain'))
            ->outputDirectory(resource_path('types'))
            ->writer(new GlobalNamespaceWriter('generated.d.ts'))
            ->formatter(PrettierFormatter::class)
            ->withoutManifest();
    }
}
