<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserQuotaLimit extends Model
{
    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasQuotaRemaining(string $type, int $amount = 1): bool
    {
        $used = $this->getAttribute("{$type}_used");
        $limit = $this->getAttribute("{$type}_limit");

        return ($used + $amount) <= $limit;
    }

    public function getRemainingQuota(string $type): int
    {
        $used = $this->getAttribute("{$type}_used");
        $limit = $this->getAttribute("{$type}_limit");

        return max(0, $limit - $used);
    }

    public function getUsagePercentage(string $type): float
    {
        $used = $this->getAttribute("{$type}_used");
        $limit = $this->getAttribute("{$type}_limit");

        if ($limit === 0) {
            return 0;
        }

        return min(100, ($used / $limit) * 100);
    }
}
