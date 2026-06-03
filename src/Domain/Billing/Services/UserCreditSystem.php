<?php

namespace Domain\Billing\Services;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserCredit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class UserCreditSystem
{
    private int $userId;

    private const string CREDIT_CACHE_PREFIX = 'user:{id}:credit:current';

    private const string MESSAGES_SENT_CACHE_PREFIX = 'user:{id}:credit:messages:sent';

    private const string CAN_SEND_MESSAGES_CACHE_PREFIX = 'user:{id}:can-send-messages';

    private const string MESSAGES_AVAILABLE_CACHE_PREFIX = 'user:{id}:credit:messages:available';

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Add credits for a new billing cycle.
     * This method RESETS all existing active credits before adding new ones.
     * Each billing cycle starts fresh - credits do NOT accumulate.
     */
    public function addCredit(array $quotaLimit, Carbon $endsAt): UserCredit
    {
        $startedAt = now();

        /** @var User $user */
        $user = User::find($this->userId);

        // Reset credits: Expire all existing active credits before adding new ones
        // This ensures each billing cycle starts fresh (non-accumulative)
        $user->credits()
            ->where('ends_at', '>=', now())
            ->update(['ends_at' => now()->subSecond()]);

        /** @var UserCredit $credit */
        $credit = $user->credits()->create([
            'started_at' => $startedAt,
            'messages' => $quotaLimit['messages_limit'],
            'ends_at' => $endsAt,
        ]);

        // Only create usage if it doesn't exist
        if (! $credit->usage()->exists()) {
            $credit->usage()->create();
        }

        Cache::put(
            key: template(self::CREDIT_CACHE_PREFIX, ['id' => $this->userId]),
            value: [
                'started_at' => $startedAt,
                'ends_at' => $endsAt,
            ],
            ttl: $endsAt,
        );

        Cache::put(template(self::MESSAGES_SENT_CACHE_PREFIX, ['id' => $this->userId]), 0, $endsAt);

        // Cache available messages and set can-send flag
        $this->refreshCanSendMessages();

        return $credit;
    }

    /**
     * Check if user can send messages (fast cached check).
     */
    public function canSendMessages(): bool
    {
        $cached = Cache::get(template(self::CAN_SEND_MESSAGES_CACHE_PREFIX, ['id' => $this->userId]));

        // If not cached, calculate and cache it
        if ($cached === null) {
            return $this->refreshCanSendMessages();
        }

        return $cached;
    }

    /**
     * Set the can-send-messages flag in cache.
     */
    public function setCanSendMessages(bool $canSend, ?Carbon $ttl = null): void
    {
        $ttl = $ttl ?? now()->addDay();
        $cacheId = template(self::CAN_SEND_MESSAGES_CACHE_PREFIX, ['id' => $this->userId]);

        Cache::put(
            $cacheId,
            $canSend,
            $ttl
        );
    }

    /**
     * Refresh the can-send-messages flag by checking database.
     */
    public function refreshCanSendMessages(): bool
    {
        $available = $this->refreshAvailableMessages();
        $hasMessages = $available > 0;

        $this->setCanSendMessages($hasMessages, now()->addHours(3));

        return $hasMessages;
    }

    /**
     * Refresh the available messages cache from database.
     */
    public function refreshAvailableMessages(): int
    {
        $available = $this->calculateAvailableMessages();

        $cacheId = template(self::MESSAGES_AVAILABLE_CACHE_PREFIX, ['id' => $this->userId]);

        Cache::put(
            $cacheId,
            $available,
            now()->addHour()
        );

        return $available;
    }

    /**
     * Get available messages from cache or calculate.
     */
    public function getAvailableMessages(): int
    {
        $cached = Cache::get(template(self::MESSAGES_AVAILABLE_CACHE_PREFIX, ['id' => $this->userId]));

        if ($cached !== null) {
            return max(0, (int) $cached);
        }

        return $this->refreshAvailableMessages();
    }

    /**
     * Update cache after consuming messages.
     * Called by UpdateUserCreditsJob after successful consumption.
     *
     * @param  int  $messagesUsed  Number of messages consumed (based on LLM credits multiplier)
     */
    public function updateAfterCreditConsumption(int $messagesUsed = 1): void
    {
        // Increment sent messages counter
        Cache::increment(
            key: template(self::MESSAGES_SENT_CACHE_PREFIX, ['id' => $this->userId]),
            value: $messagesUsed
        );

        // Decrement available messages cache
        Cache::decrement(
            key: template(self::MESSAGES_AVAILABLE_CACHE_PREFIX, ['id' => $this->userId]),
            value: $messagesUsed
        );
    }

    /**
     * @deprecated Use UpdateUserCreditsJob instead for proper locking
     */
    public function incrementMessagesUsage(int $amount = 1): void
    {
        Cache::increment(
            key: template(self::MESSAGES_SENT_CACHE_PREFIX, ['id' => $this->userId]),
            value: $amount
        );

        /** @var User $user */
        $user = User::find($this->userId);

        $user->credit?->usage()?->increment('messages', $amount);
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

    public function consumableMessages(bool $refresh = false): int
    {
        if ($refresh) {
            return $this->refreshAvailableMessages();
        }

        return $this->getAvailableMessages();
    }

    /**
     * Calculate available messages from database.
     *
     * Returns the remaining quota for the user's active billing period
     * (plan limit minus consumed usage), plus the sum of active addons.
     * Clamped at 0 when usage has overrun the limit.
     */
    private function calculateAvailableMessages(): int
    {
        /** @var User|null $user */
        $user = User::find($this->userId);

        if (! $user) {
            return 0;
        }

        /** @var UserCredit|null $credit */
        $credit = $user->credits()
            ->where('ends_at', '>=', now())
            ->with('usage')
            ->first();

        if (! $credit) {
            return 0;
        }

        $remaining = max(0, $credit->messages - ($credit->usage?->messages ?? 0));

        $addons = (int) $user->addons()
            ->whereIn('type', ['extra_message_credits', 'auto_recharge_credits'])
            ->where('ends_at', '>=', now())
            ->sum('amount');

        return $remaining + $addons;
    }
}
