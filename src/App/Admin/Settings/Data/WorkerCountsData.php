<?php

declare(strict_types=1);

namespace App\Admin\Settings\Data;

use Spatie\LaravelData\Data;

/**
 * The most queue workers per Horizon supervisor, as Settings → Platform →
 * Queue workers holds them (config/horizon.php names the supervisors).
 */
final class WorkerCountsData extends Data
{
    public function __construct(
        public int $chat,
        public int $ingestion,
        public int $analytics,
        public int $media,
        public int $voice,
    ) {}

    /** @return array<string, int> The counts by Horizon supervisor name. */
    public function bySupervisor(): array
    {
        return [
            'chat' => $this->chat,
            'ingestion' => $this->ingestion,
            'analytics' => $this->analytics,
            'media' => $this->media,
            'voice' => $this->voice,
        ];
    }
}
