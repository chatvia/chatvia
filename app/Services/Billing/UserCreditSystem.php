<?php

namespace App\Services\Billing;

use App\Models\Credit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class UserCreditSystem
{
    private int $userId;

    private const string CREDIT_CACHE_PREFIX = 'user:{id}:credit:current';

    private const string MESSAGES_SENT_CACHE_PREFIX = 'user:{id}:credit:messages:sent';

    private const string MESSAGES_AVAILABLE_CACHE_PREFIX = 'user:{id}:credit:messages:available';

    private const string MESSAGE_CONSUMED_ALL_TIME = 'messages:sent:all';

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function addCredit(array $quotaLimit, Carbon $endsAt): Credit
    {
        $startedAt = now();

        /** @var User $user */
        $user = User::find($this->userId);

        /** @var Credit $credit */
        $credit = $user->credits()->create([
            'messages' => $quotaLimit['messages_limit'],
            'started_at' => now(),
            'ends_at' => $endsAt,
        ]);

        $credit->usage()->create();

        Cache::put(
            key: template(self::CREDIT_CACHE_PREFIX, ['id' => $this->userId]),
            value: [
                'started_at' => $startedAt,
                'ends_at' => $endsAt,
            ],
            ttl: $endsAt,
        );

        $this->refreshAvailableMessages();

        Cache::put(template(self::MESSAGES_SENT_CACHE_PREFIX, ['id' => $this->userId]), 0, $endsAt);

        return $credit;
    }

    public function incrementMessagesUsage(int $amount = 1): void
    {
        Cache::increment(
            key: template(self::MESSAGES_SENT_CACHE_PREFIX, ['id' => $this->userId]),
            value: $amount
        );

        Cache::decrement(
            key: template(self::MESSAGES_AVAILABLE_CACHE_PREFIX, ['id' => $this->userId]),
            value: $amount
        );

        Cache::increment(self::MESSAGE_CONSUMED_ALL_TIME, $amount);

        /** @var Credit $user */
        $user = User::find($this->userId);

        $user->credit->usage()->increment('messages', $amount);
    }

    public function getTotalSentMessages(): int
    {
        return Cache::get(
            key: template(self::MESSAGES_SENT_CACHE_PREFIX, ['id' => $this->userId]),
            default: 0
        );
    }

    public function resetSentMessages(): void
    {
        Cache::put(template(self::MESSAGES_SENT_CACHE_PREFIX, ['id' => $this->userId]), 0);
    }

    public function canSendMessages(): bool
    {
        return $this->consumableMessages() !== 0;
    }

    public function consumableMessages($refresh = false): int
    {
        /** @var \App\Models\User $user */
        $user = User::find($this->userId);

        $messages = $user
            ->credits()
            ->where('ends_at', '>=', now())
            ->sum('messages');

        $addons = $user
            ->addons()
            ->select('amount')
            ->where('type', 'extra_message_credits')
            ->where('ends_at', '>=', now())
            ->sum('amount');

        return $addons + $messages;
    }

    public function refreshAvailableMessages(): void
    {
        $this->consumableMessages(true);
    }
}
