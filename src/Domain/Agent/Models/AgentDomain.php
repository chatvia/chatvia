<?php

namespace Domain\Agent\Models;

use Database\Factories\AgentDomainFactory;
use Domain\Agent\Enums\AgentDomainStatus;
use Domain\Agent\Enums\DomainCheck;
use Domain\Agent\Services\CustomDomainLookup;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The custom domain of an agent's Hosted Chat (`help.yourshop.com`), at most
 * one per agent. The customer points it at the install with a CNAME record;
 * it serves the chat once a check has reached this install through it over
 * HTTPS (`verified_at`). The check asks the domain for
 * `/.well-known/chatvia-domain/{verification_token}`, so a domain that points
 * somewhere else never verifies. A verified domain that fails
 * {@see self::FAILED_CHECKS_TO_UNVERIFY} daily checks in a row stops serving.
 *
 * @property int $id
 * @property int $agent_id
 * @property string $host
 * @property string $verification_token
 * @property Carbon|null $verified_at
 * @property Carbon|null $last_checked_at
 * @property DomainCheck|null $last_check
 * @property int $failed_checks
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent $agent
 */
#[Guarded(['id'])]
class AgentDomain extends Model
{
    /** @use HasFactory<AgentDomainFactory> */
    use HasFactory;

    public const int FAILED_CHECKS_TO_UNVERIFY = 3;

    /** How long a new domain is checked by itself; after that only "Check again" does. */
    public const int WAITING_CHECK_HOURS = 48;

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'last_check' => DomainCheck::class,
            'failed_checks' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** Whether a check has reached this install through the domain. */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function status(): AgentDomainStatus
    {
        return match (true) {
            $this->isVerified() => AgentDomainStatus::Active,
            $this->last_check === null => AgentDomainStatus::Waiting,
            default => AgentDomainStatus::Problem,
        };
    }

    /**
     * Domains the scheduled check should look at now: a new one every ten
     * minutes for its first two days, a verified one once a day.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function dueForCheck(Builder $query): void
    {
        $query->where(fn (Builder $waiting): Builder => $waiting
            ->whereNull('verified_at')
            ->where('created_at', '>=', now()->subHours(self::WAITING_CHECK_HOURS))
            ->where(fn (Builder $unchecked): Builder => $unchecked
                ->whereNull('last_checked_at')
                ->orWhere('last_checked_at', '<=', now()->subMinutes(10))))
            ->orWhere(fn (Builder $verified): Builder => $verified
                ->whereNotNull('verified_at')
                ->where('last_checked_at', '<=', now()->subDay()));
    }

    protected static function booted(): void
    {
        // The lookup mirrors every row, so any change refreshes it.
        static::saved(function (): void {
            app(CustomDomainLookup::class)->forget();
        });
        static::deleted(function (): void {
            app(CustomDomainLookup::class)->forget();
        });
    }
}
