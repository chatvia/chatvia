<?php

namespace App\Services\Billing;

use App\Models\User;
use App\Models\UserQuota;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuotaService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const CACHE_PREFIX = 'user_quota:';

    /**
     * Get user quota with caching
     */
    public function getUserQuota(User $user): UserQuota
    {
        $cacheKey = self::CACHE_PREFIX . $user->id;
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            return $user->quota ?? $this->createDefaultQuota($user);
        });
    }

    /**
     * Increment usage for a specific resource type
     */
    public function incrementUsage(User $user, string $type, int $amount = 1): bool
    {
        // Check if user would exceed limit before attempting increment
        if (!$this->hasQuotaRemaining($user, $type, $amount)) {
            Log::warning("User {$user->id} would exceed {$type} quota", [
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $amount
            ]);
            return false;
        }

        // Atomic increment using optimistic locking
        $updated = DB::table('user_quotas')
            ->where('user_id', $user->id)
            ->where("{$type}_used", '<=', DB::raw("{$type}_limit - {$amount}")) // Ensure we don't exceed
            ->update([
                "{$type}_used" => DB::raw("{$type}_used + {$amount}"),
                'updated_at' => now(),
            ]);

        if ($updated) {
            // Clear cache to force refresh
            $this->clearUserQuotaCache($user);
            
            Log::info("Incremented {$type} usage for user {$user->id}", [
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $amount
            ]);
            
            return true;
        }

        return false; // Failed to update (likely concurrent update or limit exceeded)
    }

    /**
     * Bulk update usage for multiple types
     */
    public function bulkUpdateUsage(User $user, array $changes): bool
    {
        if (empty($changes)) {
            return true;
        }

        $updateFields = [];
        foreach ($changes as $type => $amount) {
            if ($amount > 0) {
                // For positive amounts, check quota before updating
                if (!$this->hasQuotaRemaining($user, $type, $amount)) {
                    Log::warning("User {$user->id} would exceed {$type} quota in bulk update", [
                        'user_id' => $user->id,
                        'type' => $type,
                        'amount' => $amount
                    ]);
                    return false;
                }
            }
            $updateFields["{$type}_used"] = DB::raw("{$type}_used + {$amount}");
        }

        $updateFields['updated_at'] = now();

        $updated = DB::table('user_quotas')
            ->where('user_id', $user->id)
            ->update($updateFields);

        if ($updated) {
            $this->clearUserQuotaCache($user);
            Log::info("Bulk updated usage for user {$user->id}", [
                'user_id' => $user->id,
                'changes' => $changes
            ]);
            return true;
        }

        return false;
    }

    /**
     * Track removal of resources (pages, minutes, agents, etc.)
     */
    public function trackRemoval(User $user, string $type, int $amount = 1): bool
    {
        $updated = DB::table('user_quotas')
            ->where('user_id', $user->id)
            ->update([
                "{$type}_removed" => DB::raw("{$type}_removed + {$amount}"),
                'updated_at' => now(),
            ]);

        if ($updated) {
            $this->clearUserQuotaCache($user);
            
            Log::info("Tracked {$type} removal for user {$user->id}", [
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $amount
            ]);
            
            return true;
        }

        return false;
    }

    /**
     * Check if user has quota remaining for a specific resource type
     */
    public function hasQuotaRemaining(User $user, string $type, int $amount = 1): bool
    {
        $quota = $this->getUserQuota($user);
        
        // Check if billing period needs reset
        if ($this->needsBillingPeriodReset($quota)) {
            $this->resetBillingPeriod($user);
            $quota = $this->getUserQuota($user); // Refresh after reset
        }
        
        $currentUsage = $quota->getAttribute("{$type}_used");
        $limit = $quota->getAttribute("{$type}_limit");
        
        return ($currentUsage + $amount) <= $limit;
    }

    /**
     * Get remaining quota for a specific resource type
     */
    public function getRemainingQuota(User $user, string $type): int
    {
        $quota = $this->getUserQuota($user);
        $currentUsage = $quota->getAttribute("{$type}_used");
        $limit = $quota->getAttribute("{$type}_limit");
        
        return max(0, $limit - $currentUsage);
    }

    /**
     * Get usage percentage for a specific resource type
     */
    public function getUsagePercentage(User $user, string $type): float
    {
        $quota = $this->getUserQuota($user);
        $currentUsage = $quota->getAttribute("{$type}_used");
        $limit = $quota->getAttribute("{$type}_limit");
        
        if ($limit === 0) {
            return 0;
        }
        
        return min(100, ($currentUsage / $limit) * 100);
    }

    /**
     * Reset billing period for user
     */
    public function resetBillingPeriod(User $user): void
    {
        $quota = $this->getUserQuota($user);
        
        // Store current period in history if you implement the history table
        // $this->archiveCurrentPeriod($quota);

        // Reset current period usage
        $quota->update([
            'pages_used' => 0,
            'pages_removed' => 0,
            'minutes_used' => 0,
            'minutes_removed' => 0,
            'messages_used' => 0,
            'agents_instruction_removed' => 0,
            'agents_knowledge_removed' => 0,
            'billing_period_starts_at' => now()->startOfMonth(),
            'billing_period_ends_at' => now()->endOfMonth(),
            'last_reset_at' => now(),
        ]);

        $this->clearUserQuotaCache($user);
        
        Log::info("Reset billing period for user {$user->id}");
    }

    /**
     * Update quota limits based on user's current plan
     */
    public function updateQuotaLimitsFromPlan(User $user): bool
    {
        $packageConfig = $user->getPackageConfig();
        $quotaLimits = $this->getQuotaLimitsForUser($user, $packageConfig);

        return $this->updateQuotaLimits($user, $quotaLimits);
    }

    /**
     * Update quota limits (when user changes plan)
     */
    public function updateQuotaLimits(User $user, array $limits): bool
    {
        $quota = $this->getUserQuota($user);
        
        $updated = $quota->update($limits);
        
        if ($updated) {
            $this->clearUserQuotaCache($user);
            
            Log::info("Updated quota limits for user {$user->id}", $limits);
        }
        
        return $updated;
    }

    /**
     * Get quota summary for all resource types
     */
    public function getQuotaSummary(User $user): array
    {
        $quota = $this->getUserQuota($user);
        $types = config('billing.quota.quota_types', ['pages', 'minutes', 'messages']);
        $summary = [];
        
        foreach ($types as $type) {
            $used = $quota->getAttribute("{$type}_used");
            $limit = $quota->getAttribute("{$type}_limit");
            
            $summary[$type] = [
                'used' => $used,
                'limit' => $limit,
                'remaining' => max(0, $limit - $used),
                'percentage' => $limit > 0 ? min(100, ($used / $limit) * 100) : 0,
                'is_exceeded' => $used > $limit,
                'is_near_limit' => $limit > 0 && ($used / $limit) >= (config('billing.quota.warning_thresholds.warning', 80) / 100),
            ];
        }
        
        // Add agent-specific quotas
        $summary['agents'] = [
            'instruction_limit' => $quota->agents_instruction_limit,
            'knowledge_limit' => $quota->agents_knowledge_limit,
        ];
        
        return $summary;
    }

    /**
     * Bulk check multiple quota types
     */
    public function hasQuotaRemainingForMultiple(User $user, array $requirements): bool
    {
        foreach ($requirements as $type => $amount) {
            if (!$this->hasQuotaRemaining($user, $type, $amount)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Check if billing period needs to be reset
     */
    private function needsBillingPeriodReset(UserQuota $quota): bool
    {
        if (!$quota->billing_period_ends_at) {
            return true;
        }
        
        return now()->greaterThan($quota->billing_period_ends_at);
    }

    /**
     * Create default quota for new user based on their plan
     */
    private function createDefaultQuota(User $user): UserQuota
    {
        // Get quota limits from user's current package
        $packageConfig = $user->getPackageConfig();
        $quotaLimits = $this->getQuotaLimitsForUser($user, $packageConfig);

        return UserQuota::create([
            'user_id' => $user->id,
            'billing_period_starts_at' => now()->startOfMonth(),
            'billing_period_ends_at' => now()->endOfMonth(),
            ...$quotaLimits
        ]);
    }

    /**
     * Get appropriate quota limits based on user's billing cycle
     */
    private function getQuotaLimitsForUser(User $user, array $packageConfig): array
    {
        $quotaLimitsConfig = $packageConfig['quota_limits'] ?? [];
        
        // Determine if user is on yearly or monthly billing
        $isYearly = $this->isUserOnYearlyBilling($user);
        $billingCycle = $isYearly ? 'yearly' : 'monthly';
        
        // Get limits for the appropriate billing cycle
        $quotaLimits = $quotaLimitsConfig[$billingCycle] ?? $quotaLimitsConfig['monthly'] ?? [];
        
        // Fallback to default limits if nothing found
        if (empty($quotaLimits)) {
            $defaultLimitsConfig = config('billing.quota.default_limits', []);
            $quotaLimits = $defaultLimitsConfig[$billingCycle] ?? $defaultLimitsConfig['monthly'] ?? [
                'pages_limit' => 100,
                'minutes_limit' => 30,
                'messages_limit' => 100,
                'agents_instruction_limit' => 1,
                'agents_knowledge_limit' => 1,
            ];
        }
        
        return $quotaLimits;
    }

    /**
     * Check if user is on yearly billing
     */
    private function isUserOnYearlyBilling(User $user): bool
    {
        $subscription = $user->subscription();
        
        if (!$subscription) {
            return false;
        }
        
        // Check if the current subscription is yearly based on price ID
        $currentPriceId = $subscription->stripe_price;
        $packages = config('billing.packages', []);
        
        foreach ($packages as $package) {
            if ($package['stripe_yearly_price_id'] === $currentPriceId) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Clear user quota cache
     */
    private function clearUserQuotaCache(User $user): void
    {
        $cacheConfig = config('billing.quota.cache', []);
        $prefix = $cacheConfig['prefix'] ?? self::CACHE_PREFIX;
        Cache::forget($prefix . $user->id);
    }

    /**
     * Clear all quota caches (useful for maintenance)
     */
    public function clearAllQuotaCaches(): void
    {
        $cacheConfig = config('billing.quota.cache', []);
        $tags = $cacheConfig['tags'] ?? ['user_quotas'];
        
        // This is a simple approach - in production you might want a more sophisticated cache invalidation
        Cache::tags($tags)->flush();
    }
} 