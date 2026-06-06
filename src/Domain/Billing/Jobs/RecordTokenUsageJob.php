<?php

namespace Domain\Billing\Jobs;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Events\TokenBalanceLow;
use Domain\Billing\Models\TokenCharge;
use Domain\Billing\Services\ChatterTokenAllowance;
use Domain\Billing\Services\UserTokenSystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Charges one assistant turn's REAL token usage to the workspace owner's pool
 * (the gating balance) and, when the turn belongs to a chatter, to that member's
 * monthly token budget — both from the single real-token source.
 *
 * Idempotent: a `token_charges` ledger row keyed on the turn id is inserted
 * first; a retried job hits the unique constraint and skips, so the atomic
 * `tokens = tokens + n` increment can never double-charge. The ledger survives
 * cache eviction (unlike a cache-key guard).
 *
 * Runs on the `messages` queue under Horizon — restart the worker after deploys.
 */
class RecordTokenUsageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId,
        public int $tokens,
        public string $turnKey,
        public ?int $chatterUserId = null,
    ) {
        $this->onQueue('messages');
    }

    public function handle(UserTokenSystem $tokenSystem, ChatterTokenAllowance $chatterAllowance): void
    {
        if ($this->tokens <= 0) {
            return;
        }

        // Idempotency gate: one ledger row per turn. A duplicate turn_key (job
        // retry / redelivery) throws on the unique index — we treat that as
        // "already charged" and stop before touching any balance.
        try {
            TokenCharge::query()->create([
                'user_id' => $this->userId,
                'turn_key' => $this->turnKey,
                'tokens' => $this->tokens,
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return;
            }

            throw $e;
        }

        // Owner pool — the authoritative send-gate balance.
        $tokenSystem->setUserId($this->userId)->recordTokenUsage($this->tokens);

        // Per-member budget (real tokens), when the turn was sent by a chatter.
        if ($this->chatterUserId !== null) {
            $chatter = User::find($this->chatterUserId);

            if ($chatter) {
                $chatterAllowance->recordTokenUsage($chatter, $this->tokens);
            }
        }

        $this->maybeTriggerAutoRecharge($tokenSystem);
    }

    /**
     * 23505 = Postgres unique_violation; 23000 = generic integrity (MySQL/SQLite).
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        return in_array((string) $e->getCode(), ['23505', '23000'], true);
    }

    /**
     * After consumption, dispatch `TokenBalanceLow` if the available token
     * balance has crossed the configured auto-recharge threshold. The
     * TriggerAutoRecharge listener debounces back-to-back fires.
     */
    private function maybeTriggerAutoRecharge(UserTokenSystem $tokenSystem): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        $billing = $user->accountBilling();

        if (! $billing || ! $billing->auto_recharge_enabled) {
            return;
        }

        $available = $tokenSystem->getAvailableTokens();

        if ($available > (int) $billing->auto_recharge_threshold) {
            return;
        }

        TokenBalanceLow::dispatch($this->userId, $available);
    }
}
