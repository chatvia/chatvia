<?php

namespace App\Data\Ingestion\OCR;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class PageData extends Data
{
    public function __construct(
        public int $pageNumber,
        public string $content,
        public string $checksum,
        public array $imagesBase64 = [],
        public array $imagePaths = [],
        public array $tables = [],
        public array $hyperlinks = [],
    ) {}

    public static function fromJson(string $json): Collection
    {
        $pages = json_decode($json, true);

        return collect($pages)->map(fn ($page) => new PageData(...$page));
    }
}
