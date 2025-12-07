<?php

namespace App\Models;

use App\Data\Chat\LlmModelData;
use App\Data\Resources\Billing\BillingPackageCapabilitiesData;
use App\Enums\CapabilityEnum;
use App\Services\Billing\Billing;
use App\Services\Billing\UserCapabilities;
use App\Services\Billing\UserCreditSystem;
use App\Traits\HasSettings;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Billable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Billable, HasFactory, HasRoles, HasSettings, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    protected $appends = ['plan', 'is_paid'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'trial_ends_at' => 'datetime',
            'billing_address' => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    public function messages()
    {
        return ChatMessage::query()
            ->whereHas('conversation.session.member', function ($query) {
                $query->where('user_id', $this->id);
            });
    }

    public function getCurrentPackage(): array
    {
        $stripePrice = $this->subscription()?->stripe_price;

        $billing = app(Billing::class);

        if (! $stripePrice) {
            return $billing->getFreePackage();
        }

        return $billing->getPackageByPriceId($stripePrice, true);
    }

    public function isStaff(): bool
    {
        return $this->type === 'staff';
    }

    public function isAdmin(): bool
    {
        return $this->type === 'admin';
    }

    public function isCompany(): bool
    {
        return $this->type === 'company';
    }

    public function hasActiveSubscription(): bool
    {
        if ($this->isStaff()) {
            /** @var User $user */
            $user = $this->manager;

            return $user->subscribed() || $this->onTrial();
        }

        return $this->subscribed() || $this->onTrial();
    }

    /**
     * Get the capabilities DTO for this user's plan
     */
    public function capabilities(): BillingPackageCapabilitiesData
    {
        return BillingPackageCapabilitiesData::forPlan($this->plan);
    }

    /**
     * Check if user has a specific capability
     */
    public function hasCapability(string $capability): bool
    {
        $capabilities = $this->capabilities();

        return property_exists($capabilities, $capability) && $capabilities->{$capability} === true;
    }

    /**
     * Get all capabilities for this user's plan as array
     */
    public function getAllCapabilities(): array
    {
        return $this->capabilities()->toArray();
    }

    public function cancelSubscription(): void
    {
        $this->subscription()?->cancel();
    }

    public function canUploadPages(int $amount): bool
    {
        return ($this->quota->pages_limit - $this->pagesConsumed()) <= $amount;
    }

    public function formattedLlms()
    {
        $llms = Llm::all();
        $subscribed = $this->subscribed();

        return $llms->map(function (Llm $llm) use ($subscribed) {
            $allowed = $llm->requires_paid_plan ? $subscribed : true;

            $llmData = LlmModelData::fromLlmModel($llm);
            $llmData->user_allowed_to_use = $allowed;

            return $llmData;
        });
    }

    public function llms()
    {
        return Llm::query()->when(! $this->subscribed(), function ($query) {
            $query->where('requires_paid_plan', false);
        })->get();
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class)->where('is_owner', false);
    }

    public function agentFiles()
    {
        return SourceFile::query()
            ->join('agent_sources', function ($join) {
                $join->on('source_files.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', SourceFile::class);
            })
            ->join('agents', 'agents.id', '=', 'agent_sources.agent_id')
            ->where('agents.user_id', $this->id)
            ->select('source_files.*');
    }

    public function plan(): Attribute
    {
        return Attribute::get(fn () => $this->getCurrentPackage()['id']);
    }

    public function isPaid(): Attribute
    {
        // All users are now paid (on trial or subscribed)
        return Attribute::get(fn () => $this->hasActiveSubscription());
    }

    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class);
    }

    public function credit(): HasOne
    {
        return $this->hasOne(Credit::class)->latestOfMany('ends_at');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(Addon::class);
    }

    public function syncPendingPlanChanges(): ?array
    {
        $subscription = $this->subscription();

        if (! $subscription) {
            return null;
        }

        try {
            // Sync subscription data first
            $subscription->syncStripeSubscription();

            // Get fresh metadata from Stripe
            $stripeSubscription = $subscription->asStripeSubscription();
            $metadata = $stripeSubscription->metadata->toArray();

            if (isset($metadata['pending_change']) && $metadata['pending_change'] === 'true') {
                return [
                    'from_package' => $metadata['from_package'] ?? null,
                    'to_package' => $metadata['to_package'] ?? null,
                    'new_price_id' => $metadata['new_price_id'] ?? null,
                    'is_yearly' => ($metadata['is_yearly'] ?? 'false') === 'true',
                    'scheduled_at' => $metadata['scheduled_at'] ?? null,
                    'effective_date' => $stripeSubscription->current_period_end * 1000, // Convert to milliseconds for JS
                ];
            }

            return null;

        } catch (\Exception $e) {
            \Log::warning('Failed to sync pending plan changes', [
                'user_id' => $this->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function usage(): HasOneThrough
    {
        return $this->hasOneThrough(
            Usage::class,           // Final model we want
            Credit::class,          // Intermediate model
            'user_id',             // Foreign key on credits table (credits.user_id)
            'credit_id',          // Foreign key on usages table (usages.credit_id)
            'id',                  // Local key on users table (users.id)
            'id'              // Local key on credits table (credits.id)
        )->latest('ends_at');
    }

    public function canPerform(CapabilityEnum $capability): bool
    {
        return app(UserCapabilities::class)->setUser($this)->can($capability);
    }

    public function creditSystem(): UserCreditSystem
    {
        return app(UserCreditSystem::class)->setUserId($this->id);
    }

    public function addCredit($quota, Carbon $endsAt): Credit
    {
        return $this->creditSystem()->addCredit($quota, $endsAt);
    }

    public function getUserId()
    {
        return $this->id;
    }

    public function pagesConsumed(): int
    {
        // Get unique files (avoid counting duplicates when file is linked to multiple agents)
        return $this
            ->agentFiles()
            ->whereIn('source_files.filetype', ['pdf', 'word', 'markdown', 'text'])
            ->groupBy('source_files.id', 'source_files.length')
            ->pluck('source_files.length')
            ->sum();
    }

    public function minutesConsumed(): int
    {
        // Get unique files (avoid counting duplicates when file is linked to multiple agents)
        return $this
            ->agentFiles()
            ->whereIn('source_files.filetype', ['audio', 'video'])
            ->groupBy('source_files.id', 'source_files.length')
            ->pluck('source_files.length')
            ->sum();
    }

    public function linksConsumed(): int
    {
        return $this
            ->agents()
            ->join('agent_sources', function ($join) {
                $join->on('agents.id', '=', 'agent_sources.agent_id')
                    ->where('agent_sources.sourceable_type', '=', SourceWebsite::class);
            })
            ->join('source_websites', 'source_websites.id', '=', 'agent_sources.sourceable_id')
            ->join('source_website_urls', 'source_websites.id', '=', 'source_website_urls.source_website_id')
            ->whereNotNull('source_website_urls.crawled_at')
            ->distinct('source_website_urls.id')
            ->count('source_website_urls.id');
    }

    public function consumableMessages(): int
    {
        return $this->creditSystem()->consumableMessages();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->type === 'admin';
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function memberOwner()
    {
        return $this->hasOne(Member::class)->where('is_owner', true)->latestOfMany();
    }

    public function quota(): HasOne
    {
        return $this->hasOne(UserQuota::class);
    }

    public function sourceFiles()
    {
        return $this->hasMany(SourceFile::class);
    }

    /**
     * Get all teams the user belongs to.
     */
    public function teams(): BelongsToMany
    {
        return $this
            ->belongsToMany(Team::class, 'team_user')
            ->withPivot('role_id', 'is_owner')
            ->withTimestamps();
    }

    /**
     * Get all teams owned by the user.
     */
    public function ownedTeams(): BelongsToMany
    {
        return $this->teams()->wherePivot('is_owner', true);
    }

    /**
     * Get teams where user is a member (not owner).
     */
    public function memberTeams(): BelongsToMany
    {
        return $this->teams()->wherePivot('is_owner', false);
    }

    /**
     * Check if user owns a specific team.
     */
    public function ownsTeam(Team $team): bool
    {
        return $this->ownedTeams()->where('teams.id', $team->id)->exists();
    }

    /**
     * Check if user is a member of a team (but not owner).
     */
    public function belongsToTeam(Team $team): bool
    {
        return $this->teams->contains($team);
    }

    /**
     * Get the user's role in a specific team.
     */
    public function teamRole(Team $team): ?Role
    {
        $membership = $this->teams()->where('teams.id', $team->id)->first();

        if (! $membership) {
            return null;
        }

        return Role::find($membership->pivot->role_id);
    }

    /**
     * Check if user has a specific permission in a team.
     */
    public function hasTeamPermission(Team $team, string $permission): bool
    {
        $role = $this->teamRole($team);

        if (! $role) {
            return false;
        }

        return $role->hasPermissionTo($permission);
    }

    /**
     * Get the user's permissions in a specific team.
     */
    public function teamPermissions(Team $team)
    {
        $role = $this->teamRole($team);

        if (! $role) {
            return [];
        }

        return $role->permissions->pluck('name')->toArray();
    }

    /**
     * Get the user's primary team (first owned team created at registration).
     */
    public function primaryTeam()
    {
        return $this->ownedTeams()->oldest()->first();
    }

    /**
     * Check if a team is the user's primary team.
     */
    public function isPrimaryTeam($team): bool
    {
        $primaryTeam = $this->primaryTeam();

        return $primaryTeam && $primaryTeam->id === $team->id;
    }

    /**
     * Get the user who manages this user (the team owner who invited them).
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'managed_by_user_id');
    }

    /**
     * Get the user's current active team.
     */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * Switch to a different team.
     */
    public function switchTeam(Team $team): bool
    {
        if (! $this->ownsTeam($team) && ! $this->belongsToTeam($team)) {
            return false;
        }

        $this->update(['current_team_id' => $team->id]);

        return true;
    }

    /**
     * Ensure the user has a current team set.
     * Sets to first available team if not set.
     */
    public function ensureCurrentTeam(): ?Team
    {
        if ($this->current_team_id && $this->currentTeam) {
            return $this->currentTeam;
        }

        // Get the first team the user belongs to
        $team = $this->teams()->first();

        if ($team) {
            $this->update(['current_team_id' => $team->id]);

            return $team;
        }

        return null;
    }

    /**
     * Get all users managed by this user (invited via their teams).
     */
    public function managedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'managed_by_user_id');
    }

    /**
     * Check if this user was invited (managed by someone else).
     */
    public function isManagedUser(): bool
    {
        return $this->managed_by_user_id !== null;
    }
}
