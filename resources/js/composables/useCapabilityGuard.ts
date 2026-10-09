/**
 * Plan capability checks for the signed-in user (evals, guardrails, audit, …).
 * The server shares the capability map of the workspace's billing package on
 * `auth.user.capabilities`; pages use it to show a LockedFeature upgrade card
 * instead of the feature. A missing or non-`true` value counts as "not included".
 */
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

type Capability = keyof Domain.Billing.Data.Resources.Package.BillingPackageCapabilitiesData;

export function useCapabilityGuard() {
    const page = usePage<SharedData>();

    /** Whether the workspace's plan includes this capability. */
    const hasCapability = (capability: Capability): boolean => {
        return page.props.auth?.user?.capabilities?.[capability] === true;
    };

    /** Whether the plan includes every one of these capabilities. */
    const hasAllCapabilities = (requiredCapabilities: Capability[]): boolean => {
        return requiredCapabilities.every((cap) => hasCapability(cap));
    };

    /** Whether the plan includes at least one of these capabilities. */
    const hasAnyCapability = (requiredCapabilities: Capability[]): boolean => {
        return requiredCapabilities.some((cap) => hasCapability(cap));
    };

    return {
        hasCapability,
        hasAllCapabilities,
        hasAnyCapability,
    };
}
