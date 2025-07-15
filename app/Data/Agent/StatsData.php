<?php

namespace App\Data\Agent;

use Spatie\LaravelData\Data;

class StatsData extends Data
{
    public function __construct(
        public int $totalMessages,
        public int $totalMembers,
        public ?float $avgResponseTime,
        public float $satisfactionRate,
        public int $messagesLastWeek,
        public array $modelUsage,
        public array $dailyMessages,
        public array $topKnowledge,
        public \DateTimeImmutable $lastUpdated,
    ) {}
}
