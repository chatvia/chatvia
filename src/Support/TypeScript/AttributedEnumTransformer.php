<?php

namespace Support\TypeScript;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Spatie\TypeScriptTransformer\Data\TransformationContext;
use Spatie\TypeScriptTransformer\PhpNodes\PhpClassNode;
use Spatie\TypeScriptTransformer\Transformed\Transformed;
use Spatie\TypeScriptTransformer\Transformed\Untransformable;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;

/**
 * Transforms backed enums into TypeScript string-literal unions — but only the
 * ones explicitly opted in with #[TypeScript], mirroring
 * AttributedDataClassTransformer. Tag an enum when a #[TypeScript] DTO exposes
 * it as a property, otherwise that property is emitted as `undefined`.
 */
class AttributedEnumTransformer extends EnumTransformer
{
    public function transform(PhpClassNode $phpClassNode, TransformationContext $context): Transformed|Untransformable
    {
        if ($phpClassNode->getAttributes(TypeScript::class) === []) {
            return Untransformable::create();
        }

        return parent::transform($phpClassNode, $context);
    }
}
