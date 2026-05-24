<?php

namespace App\Mistral;

use Spatie\LaravelData\Data;

class MistralFileSignedUrlResponse extends Data
{
    public function __construct(
        public string $url,
    ) {}
}
