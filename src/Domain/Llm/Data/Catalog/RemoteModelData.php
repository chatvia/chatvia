<?php

declare(strict_types=1);

namespace Domain\Llm\Data\Catalog;

use Spatie\LaravelData\Data;

/**
 * One chat model as a provider lists it (`GET /models`), with what the
 * provider says about it. Anything it does not say is null.
 */
final class RemoteModelData extends Data
{
    /**
     * @param  array<string, bool>  $features  `vision`, `files` (reads PDF files), `function_calling`, `reasoning`, when the provider says
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $features = [],
    ) {}
}
