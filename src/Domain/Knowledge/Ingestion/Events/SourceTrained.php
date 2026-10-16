<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Events;

use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A file, text or website finished training for an agent and its knowledge
 * is searchable. Not fired when a scheduled website refresh succeeds.
 */
class SourceTrained
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SourceFile|SourceText|SourceWebsite $source,
        public string $agentExternalId,
    ) {}
}
