<?php

namespace App\Models;

use App\Data\Chat\LlmModelData;
use App\Data\StripeScheduleData;
use App\Enums\UserCapabilityEnum;
use App\Services\Billing\UserCapabilities;
use App\Support\Billing;
use App\Traits\HasSettings;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;

class User extends Authenticatable implements MustVerifyEmail
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
        ];
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
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

        return config("billing.packages.{$package}", config('billing.packages.free'));
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
        return ($this->credit->pages - $this->usage->pages) <= $amount;
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
        return $this->hasMany(Member::class);
    }

    public function agentFiles(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            AgentFile::class,    // Final model we want
            Agent::class,        // Intermediate model
            'user_id',          // Foreign key on agents table (agents.user_id)
            'agent_id',        // Foreign key on agent_files table (agent_files.agent_id)
            'id',               // Local key on users table (users.id)
            'id'          // Local key on agents table (agents.id)
        );
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

        if (!$subscription) {
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
                'error' => $e->getMessage()
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
        );
    }

    public function canPerform(UserCapabilityEnum $capability): bool
    {
        return app(UserCapabilities::class)->setUser($this)->can($capability);
    }

    public function capabilities()
    {

    }
}
