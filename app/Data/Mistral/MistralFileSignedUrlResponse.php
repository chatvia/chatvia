<?php

namespace App\Data\Mistral;

use Spatie\LaravelData\Data;

class MistralFileSignedUrlResponse extends Data
{
    public function __construct(
        public string $url,
    ) {}
}
