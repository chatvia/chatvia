<?php

declare(strict_types=1);

namespace Domain\Billing\Data\Subscriptions;

/**
 * A plan and its billing cycle — what one Stripe plan price stands for, or
 * what a renewal invoice pays for. `package` is the plan's key
 * (`billing_plans.key`).
 */
final readonly class PlanSelectionData
{
    public function __construct(
        public string $package,
        public bool $isYearly,
    ) {}
}
