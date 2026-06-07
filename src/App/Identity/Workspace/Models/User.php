<?php

namespace App\Identity\Workspace\Models;

use App\Identity\Workspace\Services\MessageCredits;
use App\Identity\Workspace\Services\WorkspaceConsumption;
use Database\Factories\UserFactory;
use Domain\Agent\Collections\AgentCollection;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\ModelSetting;
use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;
use Domain\Billing\Enums\CapabilityEnum;
use Domain\Billing\Models\UserAddon;
use Domain\Billing\Models\UserBilling;
use Domain\Billing\Models\UserCredit;
use Domain\Billing\Models\UserQuota;
use Domain\Billing\Services\Billing;
use Domain\Billing\Services\ChatterMessageAllowance;
use Domain\Billing\Services\UserCreditSystem;
use Domain\Helpdesk\Models\Ticket;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Llm\Data\LlmModelData;
use Domain\Llm\Models\Llm;
use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamRoleResolver;
use Domain\Membership\Team\Services\TeamService;
use Domain\Prompt\Models\AgentPrompt;
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
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
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
 * @property string|null $profile_image_path
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
 * @property-read UserCredit|null $chatterMessageCredit
 * @property-read Collection<int, UserCredit> $credits
 * @property-read int|null $credits_count
 * @property-read Team|null $currentTeam
 * @property-read bool $is_paid
 * @property-read Collection<int, User> $managedUsers
 * @property-read int|null $managed_users_count
 * @property-read User|null $parentWorkspace
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
 * @property-read Collection<int, Ticket> $tickets
 * @property-read int|null $tickets_count
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
#[Appends(['plan', 'is_paid', 'capabilities', 'avatar'])]
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
            'marketing_consent' => 'boolean',
            'analytics_consent' => 'boolean',
            'consent_given_at' => 'datetime',
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
    public function workspace(): self
    {
        return $this->parentWorkspace ?? $this;
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
        return $this->remember('billing', fn (): ?UserBilling => $this->workspace()->billing);
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

    /**
     * Account-scoped via workspace() — **lazy-only**. Eager-loading
     * (`with('agents')` / `load('agents')`) builds the relation on a bare
     * instance and returns the wrong rows for an employee/chatter; eager-load
     * through the workspace leg instead: `loadMissing(['parentWorkspace.agents', 'agents'])`.
     */
    public function agents(): HasMany
    {
        $workspace = $this->workspace();

        return $workspace->hasMany(Agent::class);
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
        return Attribute::get(function (): BillingPackageCapabilitiesData {
            // Resolve the governing plan id INSIDE the closure and WITHOUT
            // touching this model's own `plan` Attribute accessor. `plan`,
            // `is_paid` and `capabilities` are all #[Appends] accessors, so
            // reading `$this->plan` here re-triggers the attribute-cast merge
            // and recurses straight back into capabilities() ("Undefined
            // property: $plan"). `getCurrentPackage()` is the same source
            // `plan()` reads, minus the accessor round-trip.
            $plan = $this->workspace_id
                ? $this->parentWorkspace->plan
                : $this->getCurrentPackage()['id'];

            return BillingPackageCapabilitiesData::forPlan($plan);
        });
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
        $this->workspace()->billing?->subscription()?->cancel();
    }

    /**
     * Workspace-scoped knowledge consumption queries (pages, minutes, links)
     * and the matching upload-quota check. Lives on a small dedicated service
     * so the auth model isn't also a query layer. See {@see WorkspaceConsumption}.
     */
    public function consumption(): WorkspaceConsumption
    {
        return new WorkspaceConsumption($this);
    }

    /**
     * Workspace-scoped message-credit metering for read paths (cycle usage, base
     * + addons, available/limit math). Reads from the analytics rollup
     * (`analytics_agent_hourly_rollups`) so the figure stays in sync with the
     * usage panel and any downstream BI consumer.
     *
     * Use this for dashboard/UI surfaces ("X messages remaining this cycle"),
     * billing emails, and customer-trust math.
     *
     * For the **send-gate decision** (can this user send another message right
     * now?), call {@see creditSystem()} instead — it reads `user_credits.usage`,
     * which the message-write path increments synchronously and is the
     * authoritative counter for capacity enforcement. The two paths can
     * disagree by a rollup-write cycle; the divergence is intentional.
     */
    public function messageCredits(): MessageCredits
    {
        return new MessageCredits($this);
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

    public function plan(): Attribute
    {
        return Attribute::get(fn () => $this->getCurrentPackage()['id']);
    }

    public const PROFILE_IMAGE_DISK = 'public';

    public function avatar(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->profile_image_path
            ? Storage::disk(self::PROFILE_IMAGE_DISK)->url($this->profile_image_path)
            : null);
    }

    public function isPaid(): Attribute
    {
        // All users are now paid (on trial or subscribed)
        return Attribute::get(fn (): bool => $this->hasActiveSubscription());
    }

    /**
     * Account-scoped via workspace() — **lazy-only**. Eager-loading
     * (`with`/`load`) returns the wrong rows for an employee/chatter; eager-load
     * through the workspace leg instead: `loadMissing(['parentWorkspace.credits', 'credits'])`.
     */
    public function credits(): HasMany
    {
        $workspace = $this->workspace();

        return $workspace->hasMany(UserCredit::class);
    }

    /**
     * Account-scoped via workspace() — **lazy-only**. Eager-loading
     * (`with`/`load`) returns the wrong rows for an employee/chatter; eager-load
     * through the workspace leg instead: `loadMissing(['parentWorkspace.credit', 'credit'])`.
     */
    public function credit(): HasOne
    {
        $workspace = $this->workspace();

        return $workspace->hasOne(UserCredit::class)
            ->where('ends_at', '>=', now())
            ->latestOfMany('ends_at');
    }

    /**
     * Account-scoped via workspace() — **lazy-only**. Eager-loading
     * (`with`/`load`) returns the wrong rows for an employee/chatter; eager-load
     * through the workspace leg instead: `loadMissing(['parentWorkspace.addons', 'addons'])`.
     */
    public function addons(): HasMany
    {
        $workspace = $this->workspace();

        return $workspace->hasMany(UserAddon::class);
    }

    /**
     * Authoritative send-gate accessor: reads `user_credits.usage` (the
     * synchronous counter incremented on every message write) to decide whether
     * the next message is allowed through. Use this from
     * {@see EnsureAgentHasMessagesCapacity} and the ChatStreamService capacity
     * check.
     *
     * For dashboard/UI displays of "remaining messages this cycle", prefer
     * {@see MessageCredits()} which reads the analytics rollup and matches the
     * usage panel.
     */
    public function creditSystem(): UserCreditSystem
    {
        $workspaceId = $this->workspace()->id;

        return app(UserCreditSystem::class)->setUserId($workspaceId);
    }

    /**
     * This chatter's OWN per-chatter monthly message-limit credit (latest
     * period). Unlike {@see credit()} / {@see credits()} / {@see creditSystem()},
     * which resolve the workspace OWNER's billing pool via `workspace()`,
     * this binds literally to the chatter's own `user_credits` rows — the home
     * of the per-chatter cap managed by {@see ChatterMessageAllowance}.
     * Eager-load `chatterMessageCredit.usage` to render usage without N+1.
     *
     * @return HasOne<UserCredit, $this>
     */
    public function chatterMessageCredit(): HasOne
    {
        return $this->hasOne(UserCredit::class, 'user_id')->latestOfMany('ends_at');
    }

    public function addCredit($quota, Carbon $endsAt): UserCredit
    {
        $workspace = $this->workspace();

        return $workspace->creditSystem()->addCredit($quota, $endsAt);
    }

    public function getUserId()
    {
        return $this->id;
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

    /**
     * Account-scoped via workspace() — **lazy-only**. Eager-loading
     * (`with('quota')` / `load('quota')`) builds the relation on a bare instance
     * and returns null for an employee/chatter; eager-load through the workspace
     * leg instead: `loadMissing(['parentWorkspace.quota', 'quota'])` (see AgentTeamController).
     */
    public function quota(): HasOne
    {
        $workspace = $this->workspace();

        return $workspace->hasOne(UserQuota::class);
    }

    /**
     * Account-scoped via workspace() — **lazy-only**. Eager-loading
     * (`with`/`load`) returns the wrong rows for an employee/chatter; eager-load
     * through the workspace leg instead: `loadMissing(['parentWorkspace.sourceFiles', 'sourceFiles'])`.
     */
    public function sourceFiles()
    {
        $workspace = $this->workspace();

        return $workspace->hasMany(SourceFile::class);
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
     * Get the user's role in a specific team. Thin wrapper around
     * {@see TeamRoleResolver::roleFor}; new code should inject the resolver
     * directly and skip this shim.
     */
    public function teamRole(Team $team): ?Role
    {
        return $this->remember(
            "teamRole:{$team->id}",
            fn (): ?Role => app(TeamRoleResolver::class)->roleFor($this, $team),
        );
    }

    /** @see TeamRoleResolver::hasPermission */
    public function hasTeamPermission(Team $team, string $permission): bool
    {
        return app(TeamRoleResolver::class)->hasPermission($this, $team, $permission);
    }

    /**
     * @see TeamRoleResolver::permissions
     *
     * @return list<string>
     */
    public function teamPermissions(Team $team): array
    {
        return app(TeamRoleResolver::class)->permissions($this, $team);
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
    public function parentWorkspace(): BelongsTo
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
     * Resolve the user's current team WITHOUT persisting one (no DB writes,
     * safe to call from Inertia share lambdas and other render-time
     * contexts). Falls back to the user's primary owned team, then to the
     * first team they're a member of.
     *
     * For the writing variant — pick a current team AND persist the choice —
     * call {@see bootstrapCurrentTeam()} from a session-bootstrap middleware
     * or registration Action.
     */
    public function currentOrPrimaryTeam(): ?Team
    {
        return $this->remember('currentTeam', function (): ?Team {
            if ($this->current_team_id && $this->currentTeam) {
                return $this->currentTeam;
            }

            return $this->ownedTeams()->first() ?? $this->memberTeams()->first();
        });
    }

    /**
     * Like {@see currentOrPrimaryTeam()} but persists the choice when the user
     * had no `current_team_id` set. Call this on session bootstrap or
     * registration — NOT from render-time code (Inertia share lambda,
     * Blade composers, etc.) where a silent UPDATE is a foot-gun.
     */
    public function bootstrapCurrentTeam(): ?Team
    {
        if ($this->current_team_id && $this->currentTeam) {
            return $this->currentTeam;
        }

        $team = $this->ownedTeams()->first() ?? $this->memberTeams()->first();
        if ($team) {
            $this->update(['current_team_id' => $team->id]);
            // Invalidate the read cache so subsequent currentOrPrimaryTeam()
            // calls see the just-stamped row.
            unset($this->memo['currentTeam']);
        }

        return $team;
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

        if ($agent->user_id === $this->id) {
            return true;
        }

        $team = $this->currentTeam;

        if ($team === null) {
            return false;
        }

        return app(TeamService::class)->canAccessAgent(
            user: $this,
            team: $team,
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
        return max(0, $this->fixedQuotaLimit('teams_limit') - $this->teamsCreatedCount());
    }

    /**
     * Get remaining workspace users (employees + chatters combined)
     * the user can add under their plan's `workspace_users_limit`.
     */
    public function remainingWorkspaceUsersQuota(): int
    {
        return max(0, $this->fixedQuotaLimit('workspace_users_limit') - $this->workspaceUsersCount());
    }

    /**
     * Check if the user can create more teams.
     */
    public function canCreateTeam(): bool
    {
        return $this->teamsCreatedCount() < $this->fixedQuotaLimit('teams_limit');
    }

    /**
     * Whether this workspace can add another user — employee or chatter.
     * Both types draw from the same `workspace_users_limit` bucket.
     */
    public function canAddWorkspaceUser(): bool
    {
        return $this->workspaceUsersCount() < $this->fixedQuotaLimit('workspace_users_limit');
    }

    /**
     * This account's fixed quota limit for $key, falling back to the default
     * package's limit when no `user_quotas` row exists yet (fresh signup before
     * the first plan/webhook lands one). Centralises the null-quota handling
     * that every quota gate needs — without it `canAddWorkspaceUser()` and the
     * `remaining*` helpers would fatal on a brand-new account. The default-
     * package shape is owned by the Billing service, not this model.
     */
    private function fixedQuotaLimit(string $key): int
    {
        return (int) ($this->quota?->{$key} ?? app(Billing::class)->defaultFixedLimit($key));
    }

    /**
     * Whether this user may open the control panel. Only chatters are
     * blocked — admins, workspace owners, and employees all reach the dashboard.
     */
    public function canAccessDashboard(): bool
    {
        return $this->type !== UserType::Chatter;
    }

    /**
     * Whether the account has been switched off by a workspace admin. A
     * deactivated chatter is rejected by both widget and hosted-chat auth, so
     * the off-switch takes effect on their next request.
     */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * Switch the account off with an optional reason (shown to admins on the
     * Members page). Idempotent — re-deactivating refreshes the reason.
     */
    public function deactivate(?string $reason = null): void
    {
        $this->update([
            'deactivated_at' => now(),
            'deactivation_reason' => $reason,
        ]);
    }

    /**
     * Switch the account back on and clear the stored reason.
     */
    public function reactivate(): void
    {
        $this->update([
            'deactivated_at' => null,
            'deactivation_reason' => null,
        ]);
    }
}
