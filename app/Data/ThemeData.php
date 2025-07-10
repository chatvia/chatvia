<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class ThemeData extends Data
{
    public function __construct(
        public string $text_color,
        public string $font_family
    ) {}
}
