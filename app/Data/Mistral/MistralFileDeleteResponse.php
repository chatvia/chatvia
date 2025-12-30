<?php

namespace App\Data\Mistral;

use Spatie\LaravelData\Data;

class MistralFileDeleteResponse extends Data
{
    public function __construct(
        public string $id,
        public string $object,
        public bool $deleted,
    ) {}
}
