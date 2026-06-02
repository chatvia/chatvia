<?php

namespace App\Workspace\Models;

use App\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;
use App\Billing\Enums\CapabilityEnum;
use App\Billing\Models\UserAddon;
use App\Billing\Models\UserBilling;
use App\Billing\Models\UserCredit;
use App\Billing\Models\UserQuota;
use App\Billing\Models\UserUsage;
use App\Billing\Services\Billing;
use App\Billing\Services\UserCreditSystem;
use App\Helpdesk\Models\Ticket;
use App\Team\Models\Permission;
use App\Team\Models\Role;
use App\Team\Models\Team;
use App\Team\Services\TeamService;
use Database\Factories\UserFactory;
use Domain\Agent\Collections\AgentCollection;
use Domain\Agent\Models\Agent;
use Domain\Mcp\Models\AgentMcpServer;
use Domain\Llm\Models\Llm;
use Domain\Prompt\Models\AgentPrompt;
use Domain\Agent\Models\ModelSetting;
use Domain\Llm\Data\LlmModelData;
use Domain\Function\Models\AgentFunction;
use Domain\Source\File\Models\SourceFile;
use Domain\Source\Text\Models\SourceText;
use Domain\Source\Web\Models\SourceWebsite;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
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
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Support\Concerns\HasExternalId;
use Support\Enums\User\SocialProvider;
use Support\Enums\User\UserSource;
use Support\Enums\User\UserType;
use Support\Traits\HasSettings;

/**
 * @property int $id
 * @property string $external_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserType $type
 * @property UserSource $source
 * @property string|null $remember_token
 * @property int|null $workspace_id
 * @property int|null $current_team_id
 * @property string|null $registered_ip
 * @property string|null $registered_country_code
 * @property string|null $domain
 * @property array<string, mixed>|null $billing_address
 * @property string|null $deactivation_reason
 * @property string|null $deactivated_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Agent> $accessibleAgents
 * @property-read int|null $accessible_agents_count
 * @property-read Collection<int, UserAddon> $addons
 * @property-read int|null $addons_count
 * @property-read AgentCollection<int, Agent> $agents
 * @property-read int|null $agents_count
 * @property-read UserBilling|null $billing
 * @property-read BillingPackageCapabilitiesData $capabilities
 * @property-read Collection<int, User> $chatters
 * @property-read int|null $chatters_count
 * @property-read UserCredit|null $credit
 * @property-read Collection<int, UserCredit> $credits
 * @property-read int|null $credits_count
 * @property-read Team|null $currentTeam
 * @property-read bool $is_paid
 * @property-read Collection<int, User> $managedUsers
 * @property-read int|null $managed_users_count
 * @property-read User|null $workspace
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
 * @property-read Collection<int, UserSocial> $socials
 * @property-read int|null $socials_count
 * @property-read Collection<int, User> $staff
 * @property-read int|null $staff_count
 * @property-read Collection<int, Ticket> $tickets
 * @property-read int|null $tickets_count
 * @property-read UserUsage|null $usage
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereWorkspaceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRegisteredCountryCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRegisteredIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
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
    'two_factor_recovery_codes',
    'two_factor_secret',
])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /**
     * Per-request memo bucket — Inertia's share() lambda + User Appends fan
     * billing/team derivations out 4–6× per request. Read/write via remember().
     */
    private array $memo = [];

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasExternalId, HasFactory, HasRoles, HasSettings, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'last_login_at' => 'datetime',
            'type' => UserType::class,
            'source' => UserSource::class,
            'billing_address' => 'array',
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
     * Get the effective user (workspace owner if this is an employee/chatter,
     * otherwise self). Used for resource access like quota, agents, credits, etc.
     */
    protected function effectiveUser(): self
    {
        return $this->workspace_id ? $this->workspace : $this;
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $resolver
     * @return T
     */
    private function remember(string $key, \Closure $resolver): mixed
    {
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $resolver();
        }

        return $this->memo[$key];
    }

    /**
     * Cashier customer for this user's account — the workspace owner's
     * `UserBilling` row, or null if the workspace has none yet. Use this
     * anywhere you'd otherwise reach for `$user->subscribed()` etc.
     */
    public function accountBilling(): ?UserBilling
    {
        return $this->remember('billing', fn (): ?UserBilling => $this->effectiveUser()->billing);
    }

    /**
     * Same as `accountBilling()` but asserts the row exists. Use from code
     * that calls Cashier methods (createSetupIntent, redirectToBillingPortal,
     * findPaymentMethod, updateStripeCustomer, ...) — those live on the
     * Billable `UserBilling`, not on `User`. UserObserver guarantees every
     * registered user has a billing row, so the null path is a real bug
     * (workspace mis-wiring, observer skipped in tests) and should crash
     * loudly rather than return a misleading null.
     */
    public function accountBillingOrFail(): UserBilling
    {
        return $this->accountBilling() ?? throw new \RuntimeException(
            'No UserBilling row for user '.$this->id.'. UserObserver should have provisioned one on create.'
        );
    }

    public function agents(): HasMany
    {
        $effectiveUser = $this->effectiveUser();

        return $effectiveUser->hasMany(Agent::class);
    }

    public function getCurrentPackage(): array
    {
        return $this->remember('currentPackage', function (): array {
            $stripePrice = $this->accountBilling()?->subscription()?->stripe_price;
            $billing = app(Billing::class);

            return $stripePrice
                ? $billing->getPackageByPriceId($stripePrice, true)
                : $billing->getFreePackage();
        });
    }

    public function isAdmin(): bool
    {
        return $this->type === UserType::Admin;
    }

    public function isWorkspace(): bool
    {
        return $this->type === UserType::Workspace;
    }

    public function isEmployee(): bool
    {
        return $this->type === UserType::Employee;
    }

    public function isChatter(): bool
    {
        return $this->type === UserType::Chatter;
    }

    public function hasActiveSubscription(): bool
    {
        return $this->remember('hasActiveSubscription', function (): bool {
            $billing = $this->accountBilling();

            return (bool) ($billing?->subscribed() || $billing?->onTrial());
        });
    }

    public function capabilities(): Attribute
    {
        $plan = $this->workspace_id ? $this->workspace->plan : $this->plan;

        return Attribute::get(fn (): BillingPackageCapabilitiesData => BillingPackageCapabilitiesData::forPlan($plan));
    }

    public function hasCapability(CapabilityEnum $capability): bool
    {
        // `capabilities()` returns the Eloquent Attribute container; calling
        // it as a method on the instance bypasses the accessor and yields
        // the wrapper, not the resolved DTO. Access via the magic property
        // (`$this->capabilities`) so Eloquent runs the get-closure and we
        // get a `BillingPackageCapabilitiesData` with the named capability.
        return $this->capabilities->{$capability->value} === true;
    }

    public function cancelSubscription(): void
    {
        $this->effectiveUser()->billing?->subscription()?->cancel();
    }

    public function canUploadPages(int $amount): bool
    {
        return ($this->quota->pages_limit - $this->pagesConsumed()) <= $amount;
    }

    public function formattedLlms()
    {
        return Llm::catalog()->map(fn (Llm $llm): LlmModelData => LlmModelData::fromLlmModel($llm));
    }

    /**
     * @return Collection<int, Llm>
     */
    public function llms(): Collection
    {
        return Llm::catalog();
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

        return $effectiveUser->hasOne(UserCredit::class)
            ->where('ends_at', '>=', now())
            ->latestOfMany('ends_at');
    }

    public function addons(): HasMany
    {
        $effectiveUser = $this->effectiveUser();

        return $effectiveUser->hasMany(UserAddon::class);
    }

    public function syncPendingPlanChanges(): ?array
    {
        $subscription = $this->effectiveUser()->billing?->subscription();

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

    /**
     * @return HasMany<Ticket, $this>
     */
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

        return $this->remember("teamRole:{$team->id}", fn (): ?Role => Role::query()
            ->join('team_user', 'team_user.role_id', '=', 'roles.id')
            ->where('team_user.team_id', $team->id)
            ->where('team_user.user_id', $this->id)
            ->select('roles.*')
            ->first());
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
    public function primaryTeam(): ?Team
    {
        return $this->remember('primaryTeam', fn (): ?Team => $this->ownedTeams()->oldest()->first());
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
     * The workspace owner this employee/chatter belongs to.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(User::class, 'workspace_id');
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
     * Ensure the user has a current team set. Called from middleware,
     * permission helpers, and the Inertia share lambda.
     */
    public function ensureCurrentTeam(): ?Team
    {
        return $this->remember('currentTeam', function (): ?Team {
            if ($this->current_team_id && $this->currentTeam) {
                return $this->currentTeam;
            }

            $team = $this->ownedTeams()->first() ?? $this->memberTeams()->first();

            if ($team) {
                $this->update(['current_team_id' => $team->id]);

                return $team;
            }

            return null;
        });
    }

    /**
     * Employees (collaborators) this user manages.
     *
     * @return HasMany<User, $this>
     */
    public function managedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'workspace_id')->where('type', UserType::Employee);
    }

    /**
     * Chatters this user has provisioned (via the API).
     *
     * @return HasMany<User, $this>
     */
    public function chatters(): HasMany
    {
        return $this->hasMany(User::class, 'workspace_id')->where('type', UserType::Chatter);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'workspace_id');
    }

    /**
     * Agents a chatter has been explicitly granted access to (via the
     * `agent_user` pivot). Empty for non-chatter types; their agent reach is
     * still resolved through teams + roles via {@see TeamService}.
     *
     * @return BelongsToMany<Agent, $this>
     */
    public function accessibleAgents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_user');
    }

    /**
     * External OAuth identities linked to this user. One row per provider.
     *
     * @return HasMany<UserSocial, $this>
     */
    public function socials(): HasMany
    {
        return $this->hasMany(UserSocial::class);
    }

    /**
     * Resolve the user's provider-side id for a single OAuth provider, or null.
     */
    public function socialId(SocialProvider $provider): ?string
    {
        return $this->socials()->where('provider', $provider)->value('provider_id');
    }

    /**
     * Stripe-side billing state. Only workspace owners (and admins) ever have a
     * row; chatters and employees do not.
     *
     * @return HasOne<UserBilling, $this>
     */
    public function billing(): HasOne
    {
        return $this->hasOne(UserBilling::class);
    }

    /**
     * Check whether this user belongs to a workspace (employee / chatter).
     * Workspace owners and admins return false.
     */
    public function belongsToWorkspace(): bool
    {
        return $this->workspace_id !== null;
    }

    public function canAccessAgent(Agent $agent): bool
    {
        if ($this->isChatter()) {
            return $this->accessibleAgents()->whereKey($agent->id)->exists();
        }

        return app(TeamService::class)->canAccessAgent(
            user: $this,
            team: $this->currentTeam,
            agent: $agent,
        );
    }

    // =========================================================================
    // Team & Workspace-User Quota Methods
    // =========================================================================

    /**
     * Get the number of teams the user has created (owns).
     */
    public function teamsCreatedCount(): int
    {
        return $this->ownedTeams()->count();
    }

    /**
     * Number of team members (employees) the user has invited.
     * Informational only — the quota gate uses workspaceUsersCount().
     */
    public function teamMembersCount(): int
    {
        return $this->managedUsers()->count();
    }

    /**
     * Number of chatters provisioned via the API. Informational only —
     * the quota gate uses workspaceUsersCount().
     */
    public function chattersCount(): int
    {
        return $this->chatters()->count();
    }

    /**
     * Total users belonging to this workspace — employees + chatters
     * combined. This is the figure the per-plan quota gates against.
     */
    public function workspaceUsersCount(): int
    {
        return $this->users()->count();
    }

    /**
     * Get remaining teams the user can create.
     */
    public function remainingTeamsQuota(): int
    {
        return max(0, $this->quota->teams_limit - $this->teamsCreatedCount());
    }

    /**
     * Get remaining workspace users (employees + chatters combined)
     * the user can add under their plan's `workspace_users_limit`.
     */
    public function remainingWorkspaceUsersQuota(): int
    {
        return max(0, $this->quota->workspace_users_limit - $this->workspaceUsersCount());
    }

    /**
     * Check if the user can create more teams.
     */
    public function canCreateTeam(): bool
    {
        return $this->teamsCreatedCount() < $this->quota->teams_limit;
    }

    /**
     * Whether this workspace can add another user — employee or chatter.
     * Both types draw from the same `workspace_users_limit` bucket.
     */
    public function canAddWorkspaceUser(): bool
    {
        return $this->workspaceUsersCount() < $this->quota->workspace_users_limit;
    }

    public function staff(): HasMany
    {
        return $this->hasMany(User::class, 'workspace_id')->where('type', UserType::Employee);
    }

    /**
     * Whether this user may open the control panel. Only chatters are
     * blocked — admins, workspace owners, and employees all reach the dashboard.
     */
    public function canAccessDashboard(): bool
    {
        return $this->type !== UserType::Chatter;
    }
}
