<?php

namespace Domain\Knowledge\Ingestion\Actions\Pipeline;

use Domain\Knowledge\Ingestion\Events\SourceTrained;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Events\FileIngestionProgressed;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Events\TextIngestionStarted;
use Domain\Knowledge\Source\Text\Models\SourceText;

/**
 * Marks a file or text source as ingested and pushes the final state to the
 * agent's sources page.
 *
 * It is the `finally` step of the source's chunk batch, so it runs even when
 * some chunk jobs failed: what was chunked is searchable. The broadcast is the
 * authoritative "done" signal — per-chunk progress can stay below 100%
 * (duplicate or blank pages create no chunk), and without this event the page
 * would keep showing "ingesting". The people who train sources on the agent
 * are told it is done ({@see SourceTrained}).
 */
class CompleteSourceIngestionAction
{
    public function execute(SourceFile|SourceText $source, string $agentExternalId): void
    {
        $source->update(['status' => SourceStatus::Completed]);

        match (true) {
            $source instanceof SourceFile => FileIngestionProgressed::dispatch($source, $agentExternalId),
            $source instanceof SourceText => TextIngestionStarted::dispatch($source, $agentExternalId),
        };

        SourceTrained::dispatch($source, $agentExternalId);
    }
}
