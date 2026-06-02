<?php

namespace Domain\Source\File\Data;

use Spatie\LaravelData\Data;

class AudioInfoData extends Data
{
    public function __construct(
        public int $length,
        public bool $encrypted = false,
    ) {}
}
