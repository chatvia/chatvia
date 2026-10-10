<?php

declare(strict_types=1);

namespace Domain\Billing\Services\Catalog;

use Domain\Billing\Contracts\Entitlements;
use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;
use Domain\Billing\Data\Resources\Package\BillingPackageFeaturesData;
use Domain\Billing\Data\Resources\Package\PlanFeatureData;
use Domain\Billing\Enums\BillingInterval;
use Domain\Billing\Enums\PlanFeature;
use Domain\Billing\Models\BillingPlan;
use Illuminate\Support\Collection;

/**
 * Builds the feature bullets of a plan card from the plan itself, so the card
 * follows the plan whenever it is edited in the admin panel. The dashboard
 * renders each bullet in the viewer's language.
 *
 * The order is fixed: message volume, agents, pages, website links,
 * audio/video minutes, custom domains, the feature switches that are on,
 * teams, members, the features every plan has (MCP, function calling), chat
 * history, the highlights ticked on the plan (`highlights`), and last the
 * plan's own free-text lines (`extra_features`), as typed.
 *
 * A limit of zero is left out, and a limit of {@see Entitlements::UNLIMITED}
 * or more reads "unlimited". A plan sold through sales only shows "custom" or
 * "unlimited" instead of numbers, as its limits are agreed per contract.
 */
final readonly class PlanFeatureList
{
    /** The bullets of both billing cycles. */
    public function forPlan(BillingPlan $plan): BillingPackageFeaturesData
    {
        return new BillingPackageFeaturesData(
            monthly: $this->forInterval($plan, BillingInterval::Monthly),
            yearly: $this->forInterval($plan, BillingInterval::Yearly),
        );
    }

    /**
     * The bullets of one billing cycle, in card order.
     *
     * @return Collection<int, PlanFeatureData>
     */
    public function forInterval(BillingPlan $plan, BillingInterval $interval): Collection
    {
        $messages = $interval->isYearly() ? PlanFeature::MessagesPerYear : PlanFeature::MessagesPerMonth;

        $features = [
            $this->limit($plan, $messages, $this->quota($plan, $interval->value, 'messages_limit')),
            $this->limit($plan, PlanFeature::Agents, $this->quota($plan, 'fixed', 'agents_limit')),
            $this->limit($plan, PlanFeature::Pages, $this->quota($plan, 'fixed', 'pages_limit')),
            $this->limit($plan, PlanFeature::Links, $this->quota($plan, 'fixed', 'links_limit')),
            $this->limit($plan, PlanFeature::Minutes, $this->quota($plan, 'fixed', 'minutes_limit')),
            $this->limit($plan, PlanFeature::CustomDomains, $this->quota($plan, 'fixed', 'custom_domains_limit')),
            ...$this->switchedOn($plan->capabilities),
            $this->limit($plan, PlanFeature::Teams, $this->quota($plan, 'fixed', 'teams_limit')),
            $this->limit($plan, PlanFeature::Members, $this->quota($plan, 'fixed', 'workspace_users_limit')),
            PlanFeatureData::of(PlanFeature::Mcp),
            PlanFeatureData::of(PlanFeature::FunctionCalling),
            $this->limit($plan, PlanFeature::Retention, $plan->capabilities->retentionDays),
            ...$this->highlights($plan),
            ...$this->extraLines($plan),
        ];

        return collect($features)->filter()->values();
    }

    /** One limit bullet, or null when the plan does not include the feature. */
    private function limit(BillingPlan $plan, PlanFeature $feature, int $count): ?PlanFeatureData
    {
        return match (true) {
            $plan->contact_sales => PlanFeatureData::of($feature->forContactSales()),
            $count >= Entitlements::UNLIMITED => PlanFeatureData::of($feature->unlimited()),
            $count > 0 => PlanFeatureData::of($feature, ['count' => $count]),
            default => null,
        };
    }

    /** A limit from the plan's `quota_limits` (`monthly`, `yearly` or `fixed`), 0 when it has none. */
    private function quota(BillingPlan $plan, string $group, string $key): int
    {
        return (int) ($plan->quota_limits[$group][$key] ?? 0);
    }

    /**
     * A bullet for each feature switch that is on, in card order.
     *
     * @return list<PlanFeatureData>
     */
    private function switchedOn(BillingPackageCapabilitiesData $capabilities): array
    {
        $features = array_filter([
            $capabilities->sentiment ? PlanFeature::Sentiment : null,
            $capabilities->tone ? PlanFeature::Tone : null,
            $capabilities->topics ? PlanFeature::Topics : null,
            $capabilities->pulse ? PlanFeature::Pulse : null,
            $capabilities->handoff ? PlanFeature::Handoff : null,
            $capabilities->api ? PlanFeature::Api : null,
            $capabilities->evals ? PlanFeature::Evals : null,
            $capabilities->guardrails ? PlanFeature::Guardrails : null,
            $capabilities->audit ? PlanFeature::Audit : null,
            $capabilities->removeBranding ? PlanFeature::Branding : null,
            $capabilities->security ? PlanFeature::NetworkAccess : null,
            $capabilities->siemWebhook ? PlanFeature::SiemWebhook : null,
        ]);

        return array_values(array_map(PlanFeatureData::of(...), $features));
    }

    /**
     * The highlights ticked on the plan, in the order of
     * {@see PlanFeature::highlights()}; unknown values are skipped.
     *
     * @return list<PlanFeatureData>
     */
    private function highlights(BillingPlan $plan): array
    {
        $ticked = $plan->highlights ?? [];

        return array_values(array_map(
            PlanFeatureData::of(...),
            array_filter(PlanFeature::highlights(), fn (PlanFeature $feature): bool => in_array($feature->value, $ticked, true)),
        ));
    }

    /**
     * The plan's free-text lines, without blank ones.
     *
     * @return list<PlanFeatureData>
     */
    private function extraLines(BillingPlan $plan): array
    {
        $lines = array_filter(
            array_map(trim(...), $plan->extra_features),
            fn (string $line): bool => $line !== '',
        );

        return array_values(array_map(PlanFeatureData::freeText(...), $lines));
    }
}
