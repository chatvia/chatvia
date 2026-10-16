<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Events;

use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Training a file, text or website failed for good (the queue gave up on
 * it). A single website page that fails is not this: the website still
 * finishes with the pages that worked.
 */
class SourceTrainingFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SourceFile|SourceText|SourceWebsite $source,
        public string $agentExternalId,
    ) {}
}
