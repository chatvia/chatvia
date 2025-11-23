<?php

namespace App\Models;

use App\Data\Chat\LlmModelData;
use App\Enums\PermissionEnum;
use App\Enums\UserCapabilityEnum;
use App\Services\Billing\Billing;
use App\Services\Billing\UserCapabilities;
use App\Services\Billing\UserCreditSystem;
use App\Traits\HasSettings;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Laravel\Cashier\Billable;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Billable, HasFactory, HasSettings, Notifiable;

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

    public function getPackageConfig(): array
    {
        $package = $this->getCurrentPackage();

        return config('billing.packages.'.strtolower($package['name']), config('billing.packages.free'));
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscribed('default') || $this->onTrial();
    }

    /**
     * Check if user has a specific capability
     */
    public function hasCapability(string $capability): bool
    {
        $packageConfig = $this->getPackageConfig();
        $capabilities = $packageConfig['capabilities'] ?? [];

        if (! isset($capabilities[$capability])) {
            return false;
        }

        $value = $capabilities[$capability];

        // Handle boolean capabilities
        if (is_bool($value)) {
            return $value;
        }

        // Handle string capabilities (like support levels)
        if (is_string($value)) {
            return $value !== false && $value !== 'false' && $value !== '';
        }

        // Handle numeric capabilities (greater than 0 means enabled)
        if (is_numeric($value)) {
            return $value > 0;
        }

        // Handle array capabilities (non-empty means enabled)
        if (is_array($value)) {
            return ! empty($value);
        }

        // For 'unlimited' string or other truthy values
        return ! empty($value);
    }

    /**
     * Get the value of a specific capability
     */
    public function getCapability(string $capability, $default = null)
    {
        $packageConfig = $this->getPackageConfig();
        $capabilities = $packageConfig['capabilities'] ?? [];

        return $capabilities[$capability] ?? $default;
    }

    /**
     * Check if capability allows a specific value/amount
     */
    public function capabilityAllows(string $capability, $value = null): bool
    {
        $capabilityValue = $this->getCapability($capability);

        if ($capabilityValue === null || $capabilityValue === false) {
            return false;
        }

        // If no specific value to check, just check if capability exists
        if ($value === null) {
            return $this->hasCapability($capability);
        }

        // Handle 'unlimited' capability
        if ($capabilityValue === 'unlimited') {
            return true;
        }

        // Handle array capabilities (check if value is in allowed array)
        if (is_array($capabilityValue)) {
            return in_array($value, $capabilityValue);
        }

        // Handle numeric capabilities (check if value is within limit)
        if (is_numeric($capabilityValue) && is_numeric($value)) {
            return $value <= $capabilityValue;
        }

        // Handle string capabilities (exact match or level comparison)
        if (is_string($capabilityValue)) {
            return $capabilityValue === $value;
        }

        return false;
    }

    /**
     * Get all capabilities for this user's plan
     */
    public function getAllCapabilities(): array
    {
        $packageConfig = $this->getPackageConfig();

        return $packageConfig['capabilities'] ?? [];
    }

    // Legacy methods for backward compatibility
    public function canAccessFeature(string $feature, $value = null): bool
    {
        return $this->capabilityAllows($feature, $value);
    }

    public function getFeatureLimit(string $feature)
    {
        return $this->getCapability($feature);
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
        return Attribute::get(fn () => $this->getCurrentPackage()['id'] !== 'free');
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

    public function canPerform(UserCapabilityEnum $capability): bool
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
        return $this->is_admin;
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

    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    public function hasPermission(PermissionEnum $permissionEnum): bool
    {
        [$group, $name] = explode('.', $permissionEnum->value);

        return $this->permissions()
            ->whereRelation('permission', fn ($query) => $query->where('resource', $group)->where('action', $name))
            ->exists();
    }

    public function missingPermission(PermissionEnum $permissionEnum): bool
    {
        return ! $this->hasPermission($permissionEnum);
    }

    public function grantPermission(PermissionEnum $permissionEnum)
    {
        if ($this->hasPermission($permissionEnum)) {
            return;
        }

        $list = explode('.', $permissionEnum->value);

        if (count($list) !== 2) {
            throw new InvalidArgumentException('Invalid permission format');
        }

        $permission = Permission::firstOrCreate([
            'resource' => $list[0],
            'action' => $list[1],
        ]);

        $this->permissions()->create([
            'permission_id' => $permission->id,
        ]);
    }

    public function permissionsKeyValue(): ?array
    {
        // Get user's actual permissions
        $userPermissions = $this?->permissions()->with('permission')
            ->get()
            ->groupBy('permission.resource')
            ->map(function ($permissions) {
                return $permissions->pluck('permission.action')->toArray();
            })
            ->toArray();

        // Get all possible permissions from PermissionEnum
        $allPermissions = [];
        foreach (PermissionEnum::cases() as $permission) {
            [$resource, $action] = explode('.', $permission->value);

            if (! isset($allPermissions[$resource])) {
                $allPermissions[$resource] = [];
            }

            // Check if user has this permission
            $hasPermission = isset($userPermissions[$resource]) &&
                            in_array($action, $userPermissions[$resource]);

            $allPermissions[$resource][$action] = $hasPermission;
        }

        return $allPermissions;
    }

    public function sourceFiles()
    {
        return $this->hasMany(SourceFile::class);
    }
}
