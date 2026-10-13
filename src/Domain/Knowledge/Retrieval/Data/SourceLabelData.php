<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Data;

use Domain\Knowledge\Retrieval\Enums\SourceKind;
use Spatie\LaravelData\Data;

/**
 * How one retrieved chunk's source is named for the model and for people:
 * a label (file name, page title, text excerpt), a public link when there is
 * one (a website page, a video; never a stored file), and the page number of
 * a PDF.
 */
final class SourceLabelData extends Data
{
    public function __construct(
        public string $label,
        public SourceKind $kind,
        public ?string $url = null,
        public ?int $page = null,
    ) {}
}
