<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Platform;

/**
 * One point of the admin dashboard's activity series: an hour, a day or a
 * month, with the short label a chart prints under it.
 */
final readonly class ActivityBucketData
{
    public function __construct(
        /** The bucket's start, `Y-m-d H:i:s`. */
        public string $start,
        public string $label,
        public int $visitorMessages,
        public int $aiReplies,
        public int $conversations,
        public int $newWorkspaces,
        public int $newAgents,
    ) {}

    public function messages(): int
    {
        return $this->visitorMessages + $this->aiReplies;
    }
}
