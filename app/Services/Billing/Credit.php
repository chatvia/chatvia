<?php

namespace App\Services\Billing;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class Credit
{
    private int $userId;

    private const string CREDIT_CACHE_PREFIX = 'user:credit:current:';

    private const string MESSAGES_CACHE_PREFIX = 'user:credit:current:messages:';

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function addCreditForSubscription(array $quotaLimit, Carbon $endsAt): \App\Models\Credit
    {
        $startedAt = now();

        $user = User::find($this->userId);

        /** @var \App\Models\Credit $credit */
        $credit = $user->credits()->create([
            'pages' => $quotaLimit['pages_limit'],
            'minutes' => $quotaLimit['minutes_limit'],
            'messages' => $quotaLimit['messages_limit'],
            'started_at' => now(),
            'ends_at' => $endsAt,
        ]);

        $credit->usage()->create();

        Cache::put(
            key: self::CREDIT_CACHE_PREFIX.$this->userId,
            value: [
                'started_at' => $startedAt,
                'ends_at' => $endsAt,
            ],
            ttl: $endsAt,
        );

        Cache::put(self::MESSAGES_CACHE_PREFIX.$this->userId, 0, $endsAt);

        $this->resetSentMessages();

        return $credit;
    }

    public function incrementMessagesUsage(int $amount = 1): void
    {
        Cache::increment(
            key: self::MESSAGES_CACHE_PREFIX.$this->userId,
            value: $amount
        );
    }

    public function getTotalSentMessages(): int
    {
        return Cache::get(
            key: self::MESSAGES_CACHE_PREFIX.$this->userId,
            default: 0
        );
    }

    public function resetSentMessages(): void
    {
        Cache::put(self::MESSAGES_CACHE_PREFIX.$this->userId, 0);
    }

    public function canSendMessages(): bool
    {
        return $this->getTotalSentMessages() >= 1;
    }

}
