<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\File\Listeners;

use Domain\Knowledge\Ingestion\Events\SourceTrained;
use Domain\Knowledge\Source\File\Actions\FlagPersonalDataInFileAction;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Checks a file for card numbers, IBANs and the like once it is trained
 * ({@see FlagPersonalDataInFileAction}). Queued: a long file has many chunks.
 */
class FlagPersonalDataInTrainedFile implements ShouldQueue
{
    /** Run only after the dispatching transaction has committed. */
    public bool $afterCommit = true;

    public function __construct(
        private readonly FlagPersonalDataInFileAction $flag,
    ) {}

    public function handle(SourceTrained $event): void
    {
        if ($event->source instanceof SourceFile) {
            $this->flag->execute($event->source);
        }
    }
}
