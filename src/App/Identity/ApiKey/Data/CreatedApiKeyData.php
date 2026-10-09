<?php

declare(strict_types=1);

namespace App\Identity\ApiKey\Data;

use Spatie\LaravelData\Data;

/**
 * A newly created API key, including the plain-text value that is shown only
 * once.
 */
class CreatedApiKeyData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $plaintext,
    ) {}
}
