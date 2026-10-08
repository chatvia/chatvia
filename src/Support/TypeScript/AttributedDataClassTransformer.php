<?php

namespace Support\TypeScript;

use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelTypeScriptTransformer\LaravelData\Transformers\DataClassTransformer;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Spatie\TypeScriptTransformer\PhpNodes\PhpClassNode;
use Spatie\TypeScriptTransformer\PhpNodes\PhpPropertyNode;
use Spatie\TypeScriptTransformer\Transformers\ClassPropertyProcessors\ClassPropertyProcessor;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptProperty;

/**
 * Transforms spatie/laravel-data classes into TypeScript — but only the ones
 * explicitly opted in with #[TypeScript].
 *
 * The stock DataClassTransformer converts every Data class it finds, which
 * would publish hundreds of internal DTOs (Action inputs, job payloads) to the
 * frontend. Requiring the attribute keeps generated.d.ts limited to the shapes
 * the Vue pages actually consume, while still getting laravel-data aware
 * typing (Lazy, Optional, DataCollection) that the generic class transformer
 * lacks.
 *
 * Properties marked with laravel-data's #[Hidden] are never serialized, so
 * they are dropped from the generated type as well. (The package's own
 * processor only drops a property when it carries BOTH Hidden attributes.)
 */
class AttributedDataClassTransformer extends DataClassTransformer
{
    protected function shouldTransform(PhpClassNode $phpClassNode): bool
    {
        return parent::shouldTransform($phpClassNode)
            && $phpClassNode->getAttributes(TypeScript::class) !== [];
    }

    protected function classPropertyProcessors(): array
    {
        $dropDataHidden = new class implements ClassPropertyProcessor
        {
            public function execute(PhpPropertyNode $phpPropertyNode, ?TypeNode $annotation, TypeScriptProperty $property): ?TypeScriptProperty
            {
                return $phpPropertyNode->getAttributes(Hidden::class) === [] ? $property : null;
            }
        };

        return [$dropDataHidden, ...parent::classPropertyProcessors()];
    }
}
