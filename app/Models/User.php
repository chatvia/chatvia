<?php

namespace App\Models;

use App\Data\Chat\LlmModelData;
use App\Observers\UserObserver;
use App\Support\Billing;
use App\Traits\HasSettings;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;

#[ObservedBy(UserObserver::class)]
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

    public function canAccessFeature(string $feature, $value = null): bool
    {
        $packageConfig = $this->getPackageConfig();
        $limits = $packageConfig['limits'] ?? [];

        if (! isset($limits[$feature])) {
            return true; // No limit defined means unlimited access
        }

        $limit = $limits[$feature];

        // If limit is 'unlimited', allow access
        if ($limit === 'unlimited') {
            return true;
        }

        // If it's an array (like models), check if value is in the array
        if (is_array($limit)) {
            return $value === null || in_array($value, $limit);
        }

        // If value is provided, check if it's within the limit
        if ($value !== null) {
            return $value <= $limit;
        }

        // Otherwise, just return if the feature exists
        return true;
    }

    public function getFeatureLimit(string $feature)
    {
        $packageConfig = $this->getPackageConfig();

        return $packageConfig['limits'][$feature] ?? null;
    }

    public function cancelSubscription(): void
    {
        $this->subscription()?->cancel();
    }

    public function quota(): HasOne
    {
        return $this->hasOne(UserQuota::class);
    }

    public function remainingPages(): Attribute
    {
        return Attribute::get(fn () => $this->quote->pages_limit - $this->quote->pages_used);
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
}
