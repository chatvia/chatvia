<?php

namespace App\Auth\Models;

use App\Billing\Data\Resources\BillingPackageCapabilitiesData;
use App\Billing\Models\UserAddon;
use App\Billing\Models\UserCredit;
use App\Billing\Models\UserQuota;
use App\Billing\Models\UserUsage;
use App\Billing\Services\Billing;
use App\Billing\Services\UserCreditSystem;
use App\Helpdesk\Models\Ticket;
use App\Teams\Models\Permission;
use App\Teams\Models\Role;
use App\Teams\Models\Team;
use App\Teams\Services\TeamService;
use Database\Factories\UserFactory;
use Domain\Agents\Collections\AgentCollection;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentFunction;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Agents\Models\AgentPrompt;
use Domain\Agents\Models\Llm;
use Domain\Agents\Models\ModelSetting;
use Domain\Chat\Data\LlmModelData;
use Domain\Chat\Models\ChatMessage;
use Domain\Sources\Files\Models\SourceFile;
use Domain\Sources\Text\Models\SourceText;
use Domain\Sources\Web\Models\SourceWebsite;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Subscription;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Support\Concerns\HasExternalId;
use Support\Enums\CreatedVia;
use Support\Traits\HasSettings;

/**
 * @property int $id
 * @property string $external_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string $type
 * @property string|null $remember_token
 * @property int|null $managed_by_user_id
 * @property int|null $current_team_id
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property Carbon|null $trial_ends_at
 * @property string|null $google_id
 * @property string|null $microsoft_id
 * @property string|null $slack_id
 * @property string|null $linkedin_id
 * @property string|null $x_id
 * @property string|null $registered_ip
 * @property string|null $registered_country_code
 * @property string|null $domain
 * @property string|null $deactivation_reason
 * @property string|null $deactivated_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, UserAddon> $addons
 * @property-read int|null $addons_count
 * @property-read AgentCollection<int, Agent> $agents
 * @property-read int|null $agents_count
 * @property-read BillingPackageCapabilitiesData $capabilities
 * @property-read UserCredit|null $credit
 * @property-read Collection<int, UserCredit> $credits
 * @property-read int|null $credits_count
 * @property-read Team|null $currentTeam
 * @property-read bool $is_paid
 * @property-read Collection<int, User> $managedUsers
 * @property-read int|null $managed_users_count
 * @property-read User|null $manager
 * @property-read Collection<int, Team> $memberTeams
 * @property-read int|null $member_teams_count
 * @property-read Collection<int, ModelSetting> $modelSettings
 * @property-read int|null $model_settings_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, Team> $ownedTeams
 * @property-read int|null $owned_teams_count
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read mixed $plan
 * @property-read UserQuota|null $quota
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Collection<int, User> $staff
 * @property-read int|null $staff_count
 * @property-read Collection<int, Subscription> $subscriptions
 * @property-read int|null $subscriptions_count
 * @property-read Collection<int, Ticket> $tickets
 * @property-read int|null $tickets_count
 * @property-read UserUsage|null $usage
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User hasExpiredGenericTrial()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onGenericTrial()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, ?string $guard = null, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User team($teams, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCurrentTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeactivatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeactivationReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDomain($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGoogleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLinkedinId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereManagedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereMicrosoftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePmLastFour($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePmType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRegisteredCountryCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRegisteredIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSlackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStripeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTrialEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereXId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, ?string $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTeam($teams)
 *
 * @mixin \Eloquent
 */
#[Appends(['plan', 'is_paid', 'capabilities'])]
#[Guarded(['id'])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasApiTokens, HasExternalId, HasFactory, HasRoles, HasSettings, Notifiable;

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
            'created_via' => CreatedVia::class,
        ];
    }

    /**
     * Account-level webhook-backed functions this customer owns. Each can be
     * attached to any number of their agents via the `agent_function`
     * pivot — see `Agent::functions()`.
     *
     * @return HasMany<AgentFunction, $this>
     */
    public function functions(): HasMany
    {
        return $this->hasMany(AgentFunction::class);
    }

    /**
     * Account-level reusable prompts this customer owns (Settings → Prompts).
     * Each can be attached to any number of their agents via
     * `agents.agent_prompt_id` — see `Agent::agentPrompt()`.
     *
     * @return HasMany<AgentPrompt, $this>
     */
    public function prompts(): HasMany
    {
        return $this->hasMany(AgentPrompt::class);
    }

    /**
     * Account-level MCP servers this customer owns. Each can be attached to
     * any number of their agents via `agent_mcp_server`.
     *
     * @return HasMany<AgentMcpServer, $this>
     */
    public function mcpServers(): HasMany
    {
        return $this->hasMany(AgentMcpServer::class);
    }

    /**
     * True when this user is a customer's end-user (created via the API), not a
     * Chatvia account/staff member.
     */
    public function isExternal(): bool
    {
        return $this->created_via === CreatedVia::External;
    }

    /** @deprecated use isExternal() — kept for existing callers. */
    public function isExternalChatUser(): bool
    {
        return $this->isExternal();
    }

    /** @param Builder<User> $query */
    public function scopeInternal($query)
    {
        return $query->where('created_via', CreatedVia::Internal);
    }

    /** @param Builder<User> $query */
    public function scopeExternal($query)
    {
        return $query->where('created_via', CreatedVia::External);
    }

    /**
     * Get the effective user (manager if managed, otherwise self).
     * Used for resource access like quota, agents, credits, etc.
     */
    protected function effectiveUser(): self
    {
        return $this->managed_by_user_id ? $this->manager : $this;
    }

    public function agents(): HasMany
    {
        $effectiveUser = $this->effectiveUser();

        return $effectiveUser->hasMany(Agent::class);
    }

    public function messages()
    {
        return ChatMessage::query()
            ->whereHas('conversation.session.member', function ($query): void {
                $query->where('user_id', $this->id);
            });
    }

    public function getCurrentPackage(): array
    {
        $effectiveUser = $this->effectiveUser();
        $stripePrice = $effectiveUser->subscription()?->stripe_price;

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
        $effectiveUser = $this->effectiveUser();

        return $effectiveUser->subscribed() || $effectiveUser->onTrial();
    }

    public function capabilities(): Attribute
    {
        if ($this->managed_by_user_id) {
            $plan = $this->manager->plan;
        } else {
            $plan = $this->plan;
        }

        return Attribute::get(fn (): BillingPackageCapabilitiesData => BillingPackageCapabilitiesData::forPlan($plan));
    }

    public function hasCapability(string $capability): bool
    {
        $capabilities = $this->capabilities();

        return property_exists($capabilities, $capability) && $capabilities->{$capability} === true;
    }

    public function cancelSubscription(): void
    {
        $effectiveUser = $this->effectiveUser();
        $effectiveUser->subscription()?->cancel();
    }

    public function canUploadPages(int $amount): bool
    {
        return ($this->quota->pages_limit - $this->pagesConsumed()) <= $amount;
    }

    public function formattedLlms()
    {
        $llms = Llm::all();

        return $llms->map(function (Llm $llm) {
            return LlmModelData::fromLlmModel($llm);
        });
    }

    public function llms()
    {
        return Llm::all();
    }

    public function agentFiles()
    {
        $effectiveUserId = $this->effectiveUser()->id;

        return SourceFile::query()
            ->join('agent_sources', function ($join): void {
                $join->on('source_files.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', SourceFile::class);
            })
            ->join('agents', 'agents.id', '=', 'agent_sources.agent_id')
            ->where('agents.user_id', $effectiveUserId)
            ->select('source_files.*');
    }

    public function plan(): Attribute
    {
        return Attribute::get(fn () => $this->getCurrentPackage()['id']);
    }

    public function isPaid(): Attribute
    {
        // All users are now paid (on trial or subscribed)
        return Attribute::get(fn (): bool => $this->hasActiveSubscription());
    }

    public function credits(): HasMany
    {
        $effectiveUser = $this->effectiveUser();

        return $effectiveUser->hasMany(UserCredit::class);
    }

    public function credit(): HasOne
    {
        $effectiveUser = $this->effectiveUser();

        return $effectiveUser->hasOne(UserCredit::class)->latestOfMany('ends_at');
    }

    public function addons(): HasMany
    {
        $effectiveUser = $this->effectiveUser();

        return $effectiveUser->hasMany(UserAddon::class);
    }

    public function syncPendingPlanChanges(): ?array
    {
        $effectiveUser = $this->effectiveUser();
        $subscription = $effectiveUser->subscription();

        if (! $subscription) {
            return null;
        }

        try {
            // Sync subscription data first
            $subscription->syncStripeStatus();

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
                    'effective_date' => $stripeSubscription->items->data[0]->current_period_end * 1000, // Convert to milliseconds for JS
                ];
            }

            return null;

        } catch (\Exception $e) {
            Log::warning('Failed to sync pending plan changes', [
                'user_id' => $this->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function usage(): HasOneThrough
    {
        $effectiveUser = $this->effectiveUser();

        return $effectiveUser->hasOneThrough(
            UserUsage::class,           // Final model we want
            UserCredit::class,          // Intermediate model
            'user_id',             // Foreign key on credits table (credits.user_id)
            'credit_id',          // Foreign key on usages table (usages.credit_id)
            'id',                  // Local key on users table (users.id)
            'id'              // Local key on credits table (credits.id)
        )->latest('ends_at');
    }

    public function creditSystem(): UserCreditSystem
    {
        $effectiveUserId = $this->effectiveUser()->id;

        return app(UserCreditSystem::class)->setUserId($effectiveUserId);
    }

    public function addCredit($quota, Carbon $endsAt): UserCredit
    {
        $effectiveUser = $this->effectiveUser();

        return $effectiveUser->creditSystem()->addCredit($quota, $endsAt);
    }

    public function getUserId()
    {
        return $this->id;
    }

    public function pagesConsumed(): int
    {
        $effectiveUserId = $this->effectiveUser()->id;

        // Get unique SourceFiles (avoid counting duplicates when file is linked to multiple agents)
        $sourceFilesPages = SourceFile::query()
            ->whereIn('id', function ($query) use ($effectiveUserId): void {
                $query->select('agent_sources.sourceable_id')
                    ->from('agent_sources')
                    ->join('agents', 'agents.id', '=', 'agent_sources.agent_id')
                    ->where('agents.user_id', $effectiveUserId)
                    ->where('agent_sources.sourceable_type', '=', SourceFile::class)
                    ->distinct();
            })
            ->whereIn('filetype', ['pdf', 'word', 'markdown', 'text'])
            ->sum('length');

        // Get unique SourceTexts (avoid counting duplicates when text is linked to multiple agents)
        $sourceTextsPages = SourceText::query()
            ->whereIn('id', function ($query) use ($effectiveUserId): void {
                $query->select('agent_sources.sourceable_id')
                    ->from('agent_sources')
                    ->join('agents', 'agents.id', '=', 'agent_sources.agent_id')
                    ->where('agents.user_id', $effectiveUserId)
                    ->where('agent_sources.sourceable_type', '=', SourceText::class)
                    ->distinct();
            })
            ->sum('length');

        return (int) ($sourceFilesPages + $sourceTextsPages);
    }

    public function minutesConsumed(): int
    {
        $effectiveUserId = $this->effectiveUser()->id;

        // Get unique SourceFiles (avoid counting duplicates when file is linked to multiple agents)
        return (int) SourceFile::query()
            ->whereIn('id', function ($query) use ($effectiveUserId): void {
                $query->select('agent_sources.sourceable_id')
                    ->from('agent_sources')
                    ->join('agents', 'agents.id', '=', 'agent_sources.agent_id')
                    ->where('agents.user_id', $effectiveUserId)
                    ->where('agent_sources.sourceable_type', '=', SourceFile::class)
                    ->distinct();
            })
            ->whereIn('filetype', ['audio', 'video'])
            ->sum('length');
    }

    public function linksConsumed(): int
    {
        return $this
            ->agents()
            ->join('agent_sources', function ($join): void {
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

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function managerQuota() {}

    public function quota(): HasOne
    {
        $effectiveUser = $this->effectiveUser();

        return $effectiveUser->hasOne(UserQuota::class);
    }

    public function sourceFiles()
    {
        $effectiveUser = $this->effectiveUser();

        return $effectiveUser->hasMany(SourceFile::class);
    }

    /**
     * Get all teams where the user is a member (not owner).
     *
     * @return BelongsToMany<Team, $this>
     */
    public function memberTeams(): BelongsToMany
    {
        return $this
            ->belongsToMany(Team::class, 'team_user')
            ->withPivot('role_id');
    }

    /**
     * Get all teams owned by the user.
     *
     * @return HasMany<Team, $this>
     */
    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'user_id');
    }

    /**
     * Check if user owns a specific team.
     */
    public function ownsTeam(Team $team): bool
    {
        return $team->user_id === $this->id;
    }

    /**
     * Check if user is a member or owner of a team.
     */
    public function belongsToTeam(Team $team): bool
    {
        return $this->ownsTeam($team) || $this->memberTeams->contains($team);
    }

    /**
     * Get the user's role in a specific team.
     */
    public function teamRole(Team $team): ?Role
    {
        // Owner doesn't have a role in the pivot table
        if ($this->ownsTeam($team)) {
            return null;
        }

        $membership = $this->memberTeams()->where('teams.id', $team->id)->first();

        if (! $membership) {
            return null;
        }

        return Role::find($membership->pivot->getAttribute('role_id'));
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
        // Owner has all permissions
        if ($this->ownsTeam($team)) {
            return collect(config('team-permissions.permissions'))->keys()->toArray();
        }

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
     *
     * @return BelongsTo<Team, $this>
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
        if (! $this->belongsToTeam($team)) {
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

        // Get the first team the user belongs to (owned first, then member)
        $team = $this->ownedTeams()->first() ?? $this->memberTeams()->first();

        if ($team) {
            $this->update(['current_team_id' => $team->id]);

            return $team;
        }

        return null;
    }

    /**
     * Get all users managed by this user (invited via their teams).
     *
     * @return HasMany<User, $this>
     */
    public function managedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'managed_by_user_id')->where('created_via', CreatedVia::Internal);
    }

    public function externalUsers(): HasMany
    {
        return $this->hasMany(User::class, 'managed_by_user_id')->where('created_via', CreatedVia::External);
    }

    /**
     * Check if this user was invited (managed by someone else).
     */
    public function isManagedUser(): bool
    {
        return $this->managed_by_user_id !== null;
    }

    public function canAccessAgent(Agent $agent): bool
    {
        return app(TeamService::class)->canAccessAgent(
            user: $this,
            team: $this->currentTeam,
            agent: $agent,
        );
    }

    // =========================================================================
    // Team & Member Quota Methods
    // =========================================================================

    /**
     * Get the number of teams the user has created (owns).
     */
    public function teamsCreatedCount(): int
    {
        return $this->ownedTeams()->count();
    }

    /**
     * Get the number of members the user has invited across all teams.
     */
    public function teamMembersCount(): int
    {
        return $this->managedUsers()->count();
    }

    /**
     * Get remaining teams the user can create.
     */
    public function remainingTeamsQuota(): int
    {
        return max(0, $this->quota->teams_limit - $this->teamsCreatedCount());
    }

    /**
     * Get remaining members the user can invite.
     */
    public function remainingMembersQuota(): int
    {
        return max(0, $this->quota->members_limit - $this->teamMembersCount());
    }

    /**
     * Check if the user can create more teams.
     */
    public function canCreateTeam(): bool
    {
        return $this->teamsCreatedCount() < $this->quota->teams_limit;
    }

    /**
     * Check if the user can invite more members.
     */
    public function canAddTeamMember(): bool
    {
        return $this->teamMembersCount() < $this->quota->members_limit;
    }

    public function staff(): HasMany
    {
        return $this->hasMany(User::class, 'managed_by_user_id')->where('created_via', CreatedVia::Internal);
    }

    /**
     * Whether this user may open the control panel. True if they own any team or
     * belong to a team that allows dashboard access. Members of chat-only teams
     * (and users with no team) are blocked.
     */
    public function canAccessDashboard(): bool
    {
        return $this->ownedTeams()->exists()
            || $this->memberTeams()->where('teams.can_access_dashboard', true)->exists();
    }
}
