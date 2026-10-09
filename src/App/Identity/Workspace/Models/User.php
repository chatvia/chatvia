<?php

namespace App\Identity\Workspace\Models;

use App\Identity\Workspace\Concerns\HasTeams;
use App\Identity\Workspace\Concerns\ResolvesTeamMembership;
use App\Identity\Workspace\Concerns\ResolvesWorkspaceHierarchy;
use App\Identity\Workspace\Enums\DateFormat;
use App\Identity\Workspace\Enums\UserSource;
use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Services\AgentAccessPolicy;
use Database\Factories\UserFactory;
use Domain\Agent\Concerns\HasSettings;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\ModelSetting;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Domain\Billing\Concerns\InteractsWithWorkspaceBilling;
use Domain\Billing\Concerns\InteractsWithWorkspaceQuotas;
use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;
use Domain\Billing\Models\UserAddon;
use Domain\Billing\Models\UserBilling;
use Domain\Billing\Models\UserCredit;
use Domain\Billing\Models\UserQuota;
use Domain\Helpdesk\Models\Ticket;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

/**
 * An account: who someone is and what they belong to. One table holds four
 * kinds of account ({@see UserType}): platform admins, workspace owners (the
 * tenant that owns agents, billing and quotas), teammates (employees of a
 * workspace) and chatters (end-users of a workspace's agents).
 *
 * Everything account-wide resolves through {@see workspace()}: a teammate or
 * chatter reads the owner's billing, quotas and agents. The model keeps the
 * identity, casts and the knowledge/integration relations; the rest lives in
 * concern traits: {@see ResolvesWorkspaceHierarchy} (owner link, account type,
 * agent access; the rules in {@see AgentAccessPolicy}), {@see HasTeams} and
 * {@see ResolvesTeamMembership} (teams, team roles), and the thin accessors
 * over Domain\Billing in {@see InteractsWithWorkspaceBilling} and
 * {@see InteractsWithWorkspaceQuotas} (`creditSystem()`, `capabilities`,
 * `quotaLimit()`, …).
 *
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
 * @property DateFormat|null $date_format
 * @property Carbon|null $last_login_at
 * @property Carbon|null $onboarded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Agent> $accessibleAgents
 * @property-read int|null $accessible_agents_count
 * @property-read Collection<int, UserAddon> $addons
 * @property-read int|null $addons_count
 * @property-read Collection<int, Agent> $agents
 * @property-read int|null $agents_count
 * @property-read string|null $avatar
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
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasLocalePreference, MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasExternalId, HasFactory, HasRoles, HasSettings, HasTeams, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, InteractsWithWorkspaceBilling, InteractsWithWorkspaceQuotas, Notifiable, PasskeyAuthenticatable, ResolvesTeamMembership, ResolvesWorkspaceHierarchy, TwoFactorAuthenticatable;

    // Profile photos are public, small and read-heavy, so they live on the
    // CDN-backed `assets` disk (served via the pull zone) rather than the
    // Scaleway origin bucket.
    public const PROFILE_IMAGE_DISK = 'assets';

    /**
     * Per-request memo bucket — Inertia's share() lambda + User Appends fan
     * billing/team derivations out 4–6× per request. Read/write via remember().
     *
     * @var array<string, mixed>
     */
    private array $memo = [];

    /**
     * The locale Laravel should render this user's queued/mailed notifications in.
     * Null (auto) falls back to the app locale, so untranslated emails stay English.
     */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

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
            'onboarded_at' => 'datetime',
            'type' => UserType::class,
            'source' => UserSource::class,
            'date_format' => DateFormat::class,
        ];
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
     * Account-scoped via workspace() — **lazy-only**. Eager-loading
     * (`with('agents')` / `load('agents')`) builds the relation on a bare
     * instance and returns the wrong rows for an employee/chatter; eager-load
     * through the workspace leg instead: `loadMissing(['parentWorkspace.agents', 'agents'])`.
     */
    /**
     * Declared on the resolved workspace (the account owner), not necessarily
     * `$this` — hence `self` rather than `$this` as the declaring-model type.
     *
     * @return HasMany<Agent, self>
     */
    public function agents(): HasMany
    {
        $workspace = $this->workspace();

        return $workspace->hasMany(Agent::class);
    }

    /**
     * Account-scoped via workspace() — **lazy-only**. Eager-loading
     * (`with`/`load`) returns the wrong rows for an employee/chatter; eager-load
     * through the workspace leg instead: `loadMissing(['parentWorkspace.sourceFiles', 'sourceFiles'])`.
     *
     * @return HasMany<SourceFile, self>
     */
    public function sourceFiles(): HasMany
    {
        $workspace = $this->workspace();

        return $workspace->hasMany(SourceFile::class);
    }

    /**
     * Org-wide security & compliance policy for this user's account — the
     * workspace owner's `WorkspaceSecurityPolicy` row, created on first read so
     * no caller (middleware, command, Action) ever sees null. Resolved through
     * `workspace()` and memoized, mirroring `accountBilling()`, so employees and
     * chatters inherit the workspace answer through a single resolution path.
     */
    public function securityPolicy(): WorkspaceSecurityPolicy
    {
        return $this->remember(
            'securityPolicy',
            fn (): WorkspaceSecurityPolicy => WorkspaceSecurityPolicy::forWorkspace($this->workspace()->id),
        );
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
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
     * @return Attribute<string|null, never>
     */
    public function avatar(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->profile_image_path
            ? Storage::disk(self::PROFILE_IMAGE_DISK)->url($this->profile_image_path)
            : null);
    }

    /**
     * External id used as the entity-first storage prefix
     * (`users/{external_id}/…`). For a user the owner is itself.
     */
    public function getUserExternalId(): string
    {
        return $this->external_id;
    }

    /**
     * Filament panel gate (FilamentUser contract). Only internal staff
     * (UserType::Admin) may reach the admin panel; every other account type,
     * and a deactivated admin, is rejected at the panel door.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && ! $this->isDeactivated();
    }
}
