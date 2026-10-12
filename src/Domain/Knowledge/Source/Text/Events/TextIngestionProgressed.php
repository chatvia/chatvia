<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Text\Events;

use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes a text source's ingestion progress (0-100) to the agent's Text
 * sources page after each chunk job. The jobs send the batch's progress,
 * capped at 99; 100 means the text is ingested.
 */
class TextIngestionProgressed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SourceText $sourceText,
        public string $agentExternalId,
        /** The job batch's progress; null for the final event, sent once the source is ingested. */
        public ?int $progress = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agents.'.$this->agentExternalId.'.texts.progress'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TextIngestionProgressed';
    }

    /** @return array{text_id: int, progress: int} */
    public function broadcastWith(): array
    {
        return [
            'text_id' => $this->sourceText->id,
            'progress' => $this->progress(),
        ];
    }

    private function progress(): int
    {
        if ($this->sourceText->status === SourceStatus::Completed || $this->progress === null) {
            return $this->sourceText->ingestionProgress();
        }

        return min($this->progress, 99);
    }
}
