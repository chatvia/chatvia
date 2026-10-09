<?php

namespace Domain\Analytics\Data\Dashboard;

use Spatie\LaravelData\Data;

/**
 * One plan quota: how much is used, the limit, and what remains (never below 0).
 */
class QuotaUsageData extends Data
{
    public function __construct(
        public int $used,
        public int $limit,
        public int $remaining,
    ) {}

    public static function of(int $used, int $limit): self
    {
        return new self(used: $used, limit: $limit, remaining: max(0, $limit - $used));
    }
}
