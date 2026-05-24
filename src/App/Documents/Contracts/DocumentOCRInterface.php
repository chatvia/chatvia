<?php

namespace App\Documents\Contracts;

use Domain\Ingestion\Data\OCR\PageData;
use Illuminate\Support\Collection;

interface DocumentOCRInterface
{
    /**
     * @return Collection<int,PageData>
     */
    public function fromUrl(string $url): Collection;

    /**
     * @return Collection<int,PageData>
     */
    public function fromFile(string $file): Collection;
}
