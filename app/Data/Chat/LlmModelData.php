<?php

namespace App\Data\Chat;

use Spatie\LaravelData\Data;

class LlmModelData extends Data
{
    public function __construct(
        public string $id,
        public string $external_id,
        public string $name,
        public string $description,
        public int $context,
        public string $icon,
        public array $features,
        public string $location,
        public array $subscriptions = []
    ) {}
}
