<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class PdfInfoData extends Data
{
    public function __construct(
        public string $path,
        public ?string $title,
        public ?string $author,
        public ?string $subject,
        public ?string $creator,
        public ?string $producer,
        public ?string $creation_date,
        public ?string $modification_date,
        public ?string $keywords,
        public ?string $pdf_version,
        public int $pages,
        public int $file_size,
        public bool $encrypted,
        public bool $user_password,
        public bool $owner_password,
        public ?string $printing,
        public ?string $copying,
        public ?string $changing,
        public ?string $adding_notes,
        public string $form,
        public bool $javascript,
        public bool $suspects,
        public bool $optimized,
        public bool $tagged,
        public bool $linearized,
        public ?float $page_size_width,
        public ?float $page_size_height,
    ) {}
}
