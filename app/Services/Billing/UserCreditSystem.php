<?php

namespace App\Services\Billing;

use App\Models\Credit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class UserCreditSystem
{
    private int $userId;

    private const string CREDIT_CACHE_PREFIX = 'user:credit:current:';

    private const string MESSAGES_SENT_CACHE_PREFIX = 'user:credit:messages:sent:';

    private const string MESSAGES_AVAILABLE_CACHE_PREFIX = 'user:credit:messages:available:';

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function addCreditForSubscription(array $quotaLimit, Carbon $endsAt): Credit
    {
        $startedAt = now();

        /** @var User $user */
        $user = User::find($this->userId);

        /** @var Credit $credit */
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

        $this->refreshAvailableMessages();

        Cache::put(self::MESSAGES_SENT_CACHE_PREFIX.$this->userId, 0, $endsAt);

        return $credit;
    }

    public function incrementMessagesUsage(int $amount = 1): void
    {
        Cache::increment(
            key: self::MESSAGES_SENT_CACHE_PREFIX.$this->userId,
            value: $amount
        );

        Cache::decrement(
            key: self::MESSAGES_AVAILABLE_CACHE_PREFIX.$this->userId,
            value: $amount
        );
    }

    public function getTotalSentMessages(): int
    {
        return Cache::get(
            key: self::MESSAGES_SENT_CACHE_PREFIX.$this->userId,
            default: 0
        );
    }

    public function resetSentMessages(): void
    {
        Cache::put(self::MESSAGES_SENT_CACHE_PREFIX.$this->userId, 0);
    }

    public function canSendMessages(): bool
    {
        return $this->availableMessages() !== 0;
    }

    public function availableMessages($refresh = false): int
    {
        if ($refresh === false && Cache::has(self::MESSAGES_AVAILABLE_CACHE_PREFIX.$this->userId)) {
            return Cache::get(self::MESSAGES_AVAILABLE_CACHE_PREFIX.$this->userId);
        }

        /** @var \App\Models\User $credit */
        $user = User::find($this->userId);

        $messages = $user->credits()
            ->where('ends_at', '>=', now())
            ->sum('messages');

        $addons = $user
            ->addons()
            ->select('amount')
            ->where('type', 'extra_message_credits')
            ->where('ends_at', '>=', now())
            ->sum('amount');

        $total = $addons + $messages;

        Cache::put(
            key: self::MESSAGES_AVAILABLE_CACHE_PREFIX.$this->userId,
            value: $total,
            ttl: now()->addDay(),
        );

        return $total;
    }

    public function refreshAvailableMessages(): void
    {
        $this->availableMessages(true);
    }
}
