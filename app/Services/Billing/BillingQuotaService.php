<?php

namespace App\Services\Billing;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class BillingQuotaService
{
    public function __construct(
        private QuotaService $quotaService
    ) {}

    /**
     * Handle subscription plan change for a user
     */
    public function handlePlanChange(User $user, ?string $oldPlan = null, ?string $newPlan = null): bool
    {
        try {
            // Update quota limits based on new plan
            $success = $this->quotaService->updateQuotaLimitsFromPlan($user);

            if ($success) {
                Log::info("Updated quota limits for user {$user->id} due to plan change", [
                    'user_id' => $user->id,
                    'old_plan' => $oldPlan,
                    'new_plan' => $newPlan,
                ]);
            }

            return $success;
        } catch (\Exception $e) {
            Log::error("Failed to update quota limits for user {$user->id} during plan change", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'old_plan' => $oldPlan,
                'new_plan' => $newPlan,
            ]);

            return false;
        }
    }

    /**
     * Get quota limits for a specific plan and billing cycle
     */
    public function getQuotaLimitsForPlan(string $planName, bool $isYearly = false): array
    {
        $quotaLimitsConfig = config("billing.packages.{$planName}.quota_limits", []);
        $billingCycle = $isYearly ? 'yearly' : 'monthly';

        return $quotaLimitsConfig[$billingCycle] ?? $quotaLimitsConfig['monthly'] ?? [];
    }

    /**
     * Compare quota usage against plan limits
     */
    public function validateQuotaAgainstPlan(User $user, string $planName, bool $isYearly = false): array
    {
        $planLimits = $this->getQuotaLimitsForPlan($planName, $isYearly);
        $quota = $this->quotaService->getUserQuota($user);
        $violations = [];

        foreach ($planLimits as $type => $limit) {
            $used = $quota->getAttribute("{$type}_used") ?? 0;

            if ($used > $limit) {
                $violations[] = [
                    'type' => $type,
                    'used' => $used,
                    'limit' => $limit,
                    'overage' => $used - $limit,
                ];
            }
        }

        return $violations;
    }

    /**
     * Check if user can downgrade to a specific plan
     */
    public function canDowngradeToPlan(User $user, string $planName, bool $isYearly = false): array
    {
        $violations = $this->validateQuotaAgainstPlan($user, $planName, $isYearly);

        return [
            'can_downgrade' => empty($violations),
            'violations' => $violations,
        ];
    }

    /**
     * Handle downgrade with quota violations
     */
    public function handleDowngradeViolations(User $user, string $newPlan, bool $isYearly = false): array
    {
        $violations = $this->validateQuotaAgainstPlan($user, $newPlan, $isYearly);

        if (empty($violations)) {
            return ['success' => true, 'violations' => []];
        }

        // For now, we'll just return the violations
        // In the future, you might want to:
        // - Archive excess data
        // - Disable excess agents
        // - Provide grace period

        return [
            'success' => false,
            'violations' => $violations,
            'message' => 'Your current usage exceeds the new plan limits. Please reduce usage before downgrading.',
        ];
    }

    /**
     * Get quota utilization summary with billing context
     */
    public function getQuotaUtilizationWithBilling(User $user): array
    {
        $quota = $this->quotaService->getUserQuota($user);
        $packageConfig = $user->getPackageConfig();
        $summary = $this->quotaService->getQuotaSummary($user);

        return [
            'quota_summary' => $summary,
            'current_plan' => $packageConfig['name'] ?? 'Free',
            'billing_period' => [
                'starts_at' => $quota->billing_period_starts_at,
                'ends_at' => $quota->billing_period_ends_at,
                'days_remaining' => $quota->billing_period_ends_at
                    ? now()->diffInDays($quota->billing_period_ends_at, false)
                    : null,
            ],
            'upgrade_recommendations' => $this->getUpgradeRecommendations($user, $summary),
        ];
    }

    /**
     * Get upgrade recommendations based on current usage
     */
    private function getUpgradeRecommendations(User $user, array $quotaSummary): array
    {
        $recommendations = [];
        $currentPlan = $user->getCurrentPackage();
        $packages = config('billing.packages', []);

        foreach ($quotaSummary as $type => $data) {
            if ($type === 'agents' || ! is_array($data)) {
                continue;
            }

            // If user is using more than 80% of quota
            if ($data['percentage'] >= 80) {
                // Find next tier plan
                $nextPlan = $this->findNextTierPlan($currentPlan, $type, $data['used']);

                if ($nextPlan) {
                    $recommendations[] = [
                        'type' => $type,
                        'current_usage' => $data['used'],
                        'current_limit' => $data['limit'],
                        'usage_percentage' => $data['percentage'],
                        'recommended_plan' => $nextPlan['name'],
                        'new_limit' => $nextPlan['quota_limits'][$type] ?? null,
                    ];
                }
            }
        }

        return $recommendations;
    }

    /**
     * Find the next tier plan that accommodates current usage
     */
    private function findNextTierPlan(array $currentPlan, string $quotaType, int $currentUsage): ?array
    {
        $packages = config('billing.packages', []);
        $planOrder = ['free', 'starter', 'standard', 'pro']; // Define upgrade path

        $currentPlanKey = strtolower($currentPlan['name']);
        $currentIndex = array_search($currentPlanKey, $planOrder);

        if ($currentIndex === false) {
            return null;
        }

        // Look for next plan that can handle current usage
        for ($i = $currentIndex + 1; $i < count($planOrder); $i++) {
            $planKey = $planOrder[$i];
            $plan = $packages[$planKey] ?? null;

            if (! $plan) {
                continue;
            }

            $quotaLimit = $plan['quota_limits'][$quotaType] ?? 0;

            if ($quotaLimit > $currentUsage) {
                return array_merge($plan, ['key' => $planKey]);
            }
        }

        return null;
    }
}
