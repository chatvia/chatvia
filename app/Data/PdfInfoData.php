<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class PdfInfoData extends Data
{
    public function __construct(
        public string $title = '',
        public string $subject = '',
        public string $keywords = '',
        public string $author = '',
        public string $creator = '',
        public string $producer = '',
        public string $creation_date = '',
        public string $modification_date = '',
        public string $tagged = '',
        public string $form  = '',
        public bool   $javascript,
        public int    $length, // Pages
        public bool   $encrypted,
        public float  $page_size_width,
        public float  $page_size_height,
        public string $file_size,
        public float  $pdf_version,
    ) {}
}
