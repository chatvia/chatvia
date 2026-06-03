import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

type Capability = keyof App.Billing.Data.Resources.Package.BillingPackageCapabilitiesData;

export function useCapabilityGuard() {
    const page = usePage<SharedData>();

    const hasCapability = (capability: Capability): boolean => {
        const capabilities = (page.props.auth?.user?.capabilities as unknown as Record<string, boolean>) || {};
        return capabilities[capability] === true;
    };

    const hasAllCapabilities = (requiredCapabilities: Capability[]): boolean => {
        return requiredCapabilities.every((cap) => hasCapability(cap));
    };

    const hasAnyCapability = (requiredCapabilities: Capability[]): boolean => {
        return requiredCapabilities.some((cap) => hasCapability(cap));
    };

    return {
        hasCapability,
        hasAllCapabilities,
        hasAnyCapability,
    };
}
