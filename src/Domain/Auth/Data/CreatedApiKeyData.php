<?php

namespace Domain\Auth\Data;

use Spatie\LaravelData\Data;

class CreatedApiKeyData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $plaintext,
    ) {}
}
