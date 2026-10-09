<?php

namespace Domain\Billing\Services;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Credits\ConsumeMessageCreditsAction;
use Domain\Billing\Actions\Credits\StartCreditCycleAction;
use Domain\Billing\Enums\MessageCreditAddon;
use Domain\Billing\Models\UserCredit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use LogicException;

/**
 * Read side of a workspace's message-credit pool: how many messages are left
 * this billing cycle, how many were sent, and the cached "may this workspace
 * send another message" flag. The pool belongs to the workspace owner (the
 * `User` row that holds the subscription, credits and add-ons); employees and
 * chatters share it. The per-chatter cap is {@see ChatterMessageAllowance}.
 *
 * Get an instance for one workspace with {@see forWorkspace()} (or
 * `User::creditSystem()`); it returns a new object, so a shared container
 * instance is never re-pointed at another workspace. The class only reads the
 * database. It keeps its own cache entries up to date; the writes that change
 * the pool are Actions ({@see StartCreditCycleAction},
 * {@see ConsumeMessageCreditsAction}).
 *
 * The send flag folds BOTH halves of "may this workspace send another message"
 * into one cached yes/no: an active billing state (paid subscription or trial)
 * AND remaining capacity this cycle. The billing half is read from Cashier's
 * locally synced tables, and only when the flag is recomputed, so the hot path
 * stays one cache read.
 */
class UserCreditSystem
{
    private const string CYCLE_CACHE_KEY = 'workspace:{id}:credit:current';

    private const string MESSAGES_SENT_CACHE_KEY = 'workspace:{id}:credit:messages:sent';

    private const string CAN_SEND_MESSAGES_CACHE_KEY = 'workspace:{id}:can-send-messages';

    private const string MESSAGES_AVAILABLE_CACHE_KEY = 'workspace:{id}:credit:messages:available';

    public function __construct(private readonly ?int $workspaceId = null) {}

    /**
     * This service scoped to one workspace, identified by its owner's user id.
     */
    public function forWorkspace(int $workspaceId): self
    {
        return new self($workspaceId);
    }

    /**
     * Fast cached check: may the workspace send another message?
     */
    public function canSendMessages(): bool
    {
        $cached = Cache::get($this->key(self::CAN_SEND_MESSAGES_CACHE_KEY));

        return $cached === null ? $this->refreshCanSendMessages() : (bool) $cached;
    }

    /**
     * Overwrite the cached send flag (three-hour backstop by default, one day
     * when no TTL is given).
     */
    public function setCanSendMessages(bool $canSend, ?Carbon $ttl = null): void
    {
        Cache::put($this->key(self::CAN_SEND_MESSAGES_CACHE_KEY), $canSend, $ttl ?? now()->addDay());
    }

    /**
     * Recompute the send flag from the database. Both checks are folded into
     * the cached flag so {@see canSendMessages()} — which every chat turn hits —
     * never recomputes either one. An unsubscribed workspace sitting on
     * leftover credits therefore can't slip a message through any gate that
     * reads the flag (the eval runner, the capacity middleware, the chat
     * stream, channel replies).
     */
    public function refreshCanSendMessages(): bool
    {
        // Always refresh the available-messages cache (the UI reads it), then
        // AND it with the billing state.
        $canSend = $this->refreshAvailableMessages() > 0 && $this->workspaceHasActiveBilling();

        $this->setCanSendMessages($canSend, now()->addHours(3));

        return $canSend;
    }

    /**
     * Recompute and cache the messages left this cycle.
     */
    public function refreshAvailableMessages(): int
    {
        $available = $this->calculateAvailableMessages();

        Cache::put($this->key(self::MESSAGES_AVAILABLE_CACHE_KEY), $available, now()->addHour());

        return $available;
    }

    /**
     * Messages left this cycle, from the cache when it is warm.
     */
    public function getAvailableMessages(): int
    {
        $cached = Cache::get($this->key(self::MESSAGES_AVAILABLE_CACHE_KEY));

        return $cached !== null ? max(0, (int) $cached) : $this->refreshAvailableMessages();
    }

    /**
     * Messages left this cycle; pass `refresh: true` to skip the cache.
     */
    public function consumableMessages(bool $refresh = false): int
    {
        return $refresh ? $this->refreshAvailableMessages() : $this->getAvailableMessages();
    }

    /**
     * Keep the counters in step after messages were charged (called inside the
     * charge transaction of {@see ConsumeMessageCreditsAction}).
     */
    public function updateAfterCreditConsumption(int $messagesUsed = 1): void
    {
        Cache::increment($this->key(self::MESSAGES_SENT_CACHE_KEY), $messagesUsed);
        Cache::decrement($this->key(self::MESSAGES_AVAILABLE_CACHE_KEY), $messagesUsed);
    }

    /**
     * Point the cache at a freshly started cycle: its window, a zero sent
     * counter, and a recomputed send flag. Called by
     * {@see StartCreditCycleAction}.
     */
    public function startCycle(UserCredit $credit): void
    {
        Cache::put(
            key: $this->key(self::CYCLE_CACHE_KEY),
            value: ['started_at' => $credit->started_at, 'ends_at' => $credit->ends_at],
            ttl: $credit->ends_at,
        );

        Cache::put($this->key(self::MESSAGES_SENT_CACHE_KEY), 0, $credit->ends_at);

        $this->refreshCanSendMessages();
    }

    /**
     * Messages sent this cycle, from the cache when it is warm.
     */
    public function getTotalSentMessages(): int
    {
        $cached = Cache::get($this->key(self::MESSAGES_SENT_CACHE_KEY));

        return $cached !== null ? (int) $cached : $this->refreshSentMessages();
    }

    /**
     * Rebuild the sent-messages counter from the active cycle's usage row,
     * which is incremented in lockstep with the cache key. Covers a cache flush
     * or expiry mid-cycle, which would otherwise show 0 sent until the next
     * cycle.
     */
    public function refreshSentMessages(): int
    {
        $credit = $this->activeCredit();
        $sent = (int) ($credit?->usage?->messages ?? 0);

        Cache::put($this->key(self::MESSAGES_SENT_CACHE_KEY), $sent, $credit?->ends_at ?? now()->addHour());

        return $sent;
    }

    /**
     * The workspace's credit row for the current cycle, with its usage loaded.
     */
    public function activeCredit(): ?UserCredit
    {
        return $this->activeCreditOf($this->workspace());
    }

    /**
     * Whether the workspace has a paid subscription or a running trial. Reads
     * Cashier's locally synced tables (never the Stripe API), and only when the
     * flag is recomputed.
     */
    private function workspaceHasActiveBilling(): bool
    {
        return $this->workspace()?->hasActiveSubscription() ?? false;
    }

    /**
     * The remaining plan quota for the cycle (clamped at 0 when usage overran
     * it) plus every unexpired credit pack. Packs are a separate pool, so they
     * stay intact when the plan quota is used up. This is capacity only; the
     * billing check lives in the send flag.
     */
    private function calculateAvailableMessages(): int
    {
        $workspace = $this->workspace();
        $credit = $this->activeCreditOf($workspace);

        if (! $workspace instanceof User || ! $credit instanceof UserCredit) {
            return 0;
        }

        $remaining = max(0, $credit->messages - ($credit->usage?->messages ?? 0));

        $packs = (int) $workspace->addons()
            ->whereIn('type', MessageCreditAddon::values())
            ->where('ends_at', '>=', now())
            ->sum('amount');

        return $remaining + $packs;
    }

    private function activeCreditOf(?User $workspace): ?UserCredit
    {
        return $workspace?->credits()
            ->where('ends_at', '>=', now())
            ->with('usage')
            ->first();
    }

    private function workspace(): ?User
    {
        return User::find($this->workspaceId());
    }

    private function workspaceId(): int
    {
        return $this->workspaceId ?? throw new LogicException(
            'UserCreditSystem is not scoped to a workspace; call forWorkspace() first.'
        );
    }

    private function key(string $template): string
    {
        return template($template, ['id' => $this->workspaceId()]);
    }
}
