<?php

declare(strict_types=1);

namespace Domain\Billing\Services;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Enums\MessageCreditAddon;
use Illuminate\Support\Carbon;

/**
 * Message-credit figures for one workspace's current billing cycle, for
 * screens and emails ("X messages left this cycle"). Resolved through
 * `User::workspace()`, so a teammate sees the owner's billing.
 *
 *  - `used`: the cycle's billing counter (`user_usages.messages`, read through
 *    {@see UserCreditSystem}'s sent-messages cache). It covers every billed
 *    reply: chat, channels, evals, PDF-page extras and model multipliers.
 *  - `addons`: unexpired extra-message packs, a separate pool that survives a
 *    used-up plan quota.
 *  - `available = max(0, base - used) + addons`; `limit = base + addons`.
 *
 * The send decision itself is {@see UserCreditSystem::canSendMessages()}.
 */
class MessageCredits
{
    private readonly User $workspace;

    /** Memoized: available() and limit() combine the same two lookups. */
    private ?int $usedMemo = null;

    private ?int $addonsMemo = null;

    public function __construct(User $user)
    {
        $this->workspace = $user->workspace();
    }

    /** Messages billed this cycle; 0 when there is no credit row. */
    public function used(): int
    {
        return $this->usedMemo ??= $this->workspace->credit
            ? $this->workspace->creditSystem()->getTotalSentMessages()
            : 0;
    }

    /** The plan's message quota for the cycle; 0 when there is no credit row. */
    public function baseLimit(): int
    {
        return (int) ($this->workspace->credit?->messages ?? 0);
    }

    /** Sum of unexpired extra-message packs. */
    public function addons(): int
    {
        return $this->addonsMemo ??= (int) $this->workspace->addons()
            ->where('type', MessageCreditAddon::ExtraMessageCredits->value)
            ->where('ends_at', '>=', now())
            ->sum('amount');
    }

    /** Remaining plan quota (clamped at 0) plus the packs. */
    public function available(): int
    {
        return max(0, $this->baseLimit() - $this->used()) + $this->addons();
    }

    /** Everything paid for this cycle: plan quota plus packs. */
    public function limit(): int
    {
        return $this->baseLimit() + $this->addons();
    }

    /**
     * The current cycle's start and end, or null when there is no credit row.
     *
     * @return array{started_at: Carbon, ends_at: Carbon}|null
     */
    public function cycleBounds(): ?array
    {
        $credit = $this->workspace->credit;

        return $credit === null ? null : [
            'started_at' => $credit->started_at,
            'ends_at' => $credit->ends_at,
        ];
    }
}
