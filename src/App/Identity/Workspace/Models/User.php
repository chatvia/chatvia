<?php

namespace App\Identity\Workspace\Models;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Concerns\HasTeams;
use App\Identity\Workspace\Concerns\ResolvesTeamMembership;
use App\Identity\Workspace\Concerns\ResolvesWorkspaceHierarchy;
use App\Identity\Workspace\Enums\DateFormat;
use App\Identity\Workspace\Enums\OnboardingTourStage;
use App\Identity\Workspace\Services\AgentAccessPolicy;
use Database\Factories\UserFactory;
use Domain\Agent\Concerns\HasSettings;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\ModelSetting;
use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Domain\Notification\Data\NotificationPreferencesData;
use Domain\Notification\Models\UserNotification;
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
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Support\Concerns\HasExternalId;

/**
 * A staff account: who someone is and what they belong to. Every user belongs
 * to exactly one {@see Workspace} (the tenant that owns agents and settings)
 * through a {@see WorkspaceMembership}, as its owner or as a teammate; the
 * `is_admin` flag also lets platform staff open the admin panel. A
 * workspace's end-users are not users: they are {@see Chatter}s, in their own
 * table.
 *
 * Everything account-wide resolves through {@see workspace()}: owner and
 * teammates read the same {@see Workspace}, which owns the agents, knowledge,
 * tools and settings. The model keeps the identity and casts; the rest lives
 * in concern traits: {@see ResolvesWorkspaceHierarchy} (workspace membership,
 * role, admin flag, agent access; the rules in {@see AgentAccessPolicy}),
 * {@see HasTeams} and {@see ResolvesTeamMembership} (teams, team roles).
 *
 * @property int $id
 * @property string $external_id
 * @property string $name
 * @property string $email Always lowercase (a CHECK enforces it; the mutator lowers it).
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $app_authentication_secret
 * @property array<int, string>|null $app_authentication_recovery_codes
 * @property bool $is_admin
 * @property string|null $remember_token
 * @property int|null $current_team_id
 * @property string|null $registered_ip
 * @property string|null $registered_country_code
 * @property string|null $profile_image_path
 * @property string|null $deactivation_reason
 * @property Carbon|null $deactivated_at
 * @property DateFormat|null $date_format
 * @property string|null $locale
 * @property OnboardingTourStage|null $onboarding_tour
 * @property array<string, mixed>|null $notification_preferences
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $avatar
 * @property-read Team|null $currentTeam
 * @property-read WorkspaceMembership|null $workspaceMembership
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
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Collection<int, UserSocial> $socials
 * @property-read int|null $socials_count
 * @property-read Collection<int, UserNotification> $userNotifications
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRegisteredCountryCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRegisteredIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, ?string $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTeam($teams)
 *
 * @mixin \Eloquent
 */
#[Appends(['avatar'])]
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
    use HasApiTokens, HasExternalId, HasFactory, HasRoles, HasSettings, HasTeams, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable, PasskeyAuthenticatable, ResolvesTeamMembership, ResolvesWorkspaceHierarchy, TwoFactorAuthenticatable;

    // Profile photos are public, small and read-heavy, so they live on the
    // CDN-backed `assets` disk (served via the pull zone) rather than the
    // Scaleway origin bucket.
    public const string PROFILE_IMAGE_DISK = 'assets';

    /**
     * Per-request memo bucket — Inertia's share() lambda and the access checks
     * fan team derivations out several times per request. Read/write via
     * remember().
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
            'two_factor_confirmed_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_admin' => 'boolean',
            'date_format' => DateFormat::class,
            'onboarding_tour' => OnboardingTourStage::class,
            'notification_preferences' => 'array',
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
     * The security & compliance policy of this user's workspace, created on
     * first read so no caller (middleware, command, Action) ever sees null.
     * Memoized per user, so owner and teammates resolve it the same way.
     */
    public function securityPolicy(): WorkspaceSecurityPolicy
    {
        return $this->remember(
            'securityPolicy',
            fn (): WorkspaceSecurityPolicy => $this->workspace()->securityPolicy(),
        );
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
     * This person's notification center. Not `notifications()`: that name is
     * Laravel's own database channel, which the app does not use.
     *
     * @return HasMany<UserNotification, $this>
     */
    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    /**
     * Which notifications ring the bell, alert the desktop or play a sound. A
     * model built in memory (a factory, before a refresh) does not hold the
     * column yet; it has the defaults.
     */
    public function notificationPreferences(): NotificationPreferencesData
    {
        return NotificationPreferencesData::fromStored(
            array_key_exists('notification_preferences', $this->attributes) ? $this->notification_preferences : null,
        );
    }

    /**
     * How far this person got in the dashboard tour; null when not started,
     * also on a model built in memory that does not hold the column yet.
     */
    public function onboardingTourStage(): ?OnboardingTourStage
    {
        return array_key_exists('onboarding_tour', $this->attributes) ? $this->onboarding_tour : null;
    }

    /**
     * Emails are stored lowercase (the `users_email_lowercase` CHECK holds
     * it), so every lookup is a plain `email = ?` on the lowercased input
     * and the unique index serves it, whatever case the person typed.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::set(fn (string $value): string => Str::lower($value));
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
     * The entity-first storage prefix for this person's own files (profile
     * photo, settings images, data exports): `users/{external_id}`.
     */
    public function storageRoot(): string
    {
        return 'users/'.$this->external_id;
    }

    /** A person's setting images sit with their own files. */
    public function settingsStorageRoot(): string
    {
        return $this->storageRoot();
    }

    /**
     * Whether either two-step sign-in is on: the dashboard's (Fortify) or
     * the admin panel's (an authenticator app).
     */
    public function usesTwoFactor(): bool
    {
        return $this->hasEnabledTwoFactorAuthentication() || filled($this->getAppAuthenticationSecret());
    }

    /**
     * Filament panel gate (FilamentUser contract). Only platform staff
     * (`is_admin`) may reach the admin panel; everyone else, and a
     * deactivated admin, is rejected at the panel door.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && ! $this->isDeactivated();
    }
}
