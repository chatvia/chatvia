import { useBrand } from '@/composables/useBrand';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, toValue, type MaybeRefOrGetter } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PlanBillingConfig, PlanPackage } from '../types';
import { usePlanText } from './usePlanText';

interface PlanSelectionOptions {
    packages: MaybeRefOrGetter<PlanPackage[]>;
    currentPackage: MaybeRefOrGetter<string>;
    billingConfig: MaybeRefOrGetter<PlanBillingConfig>;
    isYearly: MaybeRefOrGetter<boolean>;
}

type PackageAction = 'current' | 'upgrade' | 'downgrade' | 'same';

/**
 * What picking a plan does on the plans page. A workspace without a
 * subscription goes to Stripe Checkout; a subscribed one switches plan in
 * place. Contact-sales plans (Enterprise) send the visitor to the contact page
 * instead. Also provides the button label and feature-list heading per plan.
 */
export function usePlanSelection(options: PlanSelectionOptions) {
    const { t } = useI18n();
    const { brand } = useBrand();
    const { planName, uiText } = usePlanText();
    const page = usePage();

    const isSubscribing = ref(false);
    const subscribingPackage = ref('');

    // The packages prop may arrive as an array or an id-keyed map.
    const packagesArray = (): PlanPackage[] => {
        const packages = toValue(options.packages);
        return Array.isArray(packages) ? packages : (Object.values(packages) as PlanPackage[]);
    };

    const findPackage = (packageId: string): PlanPackage | undefined => packagesArray().find((pkg) => pkg.id === packageId);

    const isCurrentPackage = (packageId: string): boolean => packageId === toValue(options.currentPackage);

    // Upgrade or downgrade is decided by price on the selected billing cycle.
    const getPackageAction = (packageId: string): PackageAction => {
        if (isCurrentPackage(packageId)) {
            return 'current';
        }

        const currentPkg = findPackage(toValue(options.currentPackage));
        const targetPkg = findPackage(packageId);

        if (!currentPkg || !targetPkg) {
            return packageId === 'free' ? 'downgrade' : 'upgrade';
        }

        const yearly = toValue(options.isYearly);
        const currentPrice = yearly ? currentPkg.yearly_price : currentPkg.monthly_price;
        const targetPrice = yearly ? targetPkg.yearly_price : targetPkg.monthly_price;

        if (targetPrice > currentPrice) {
            return 'upgrade';
        } else if (targetPrice < currentPrice) {
            return 'downgrade';
        } else {
            return 'same';
        }
    };

    const getButtonText = (packageId: string): string => {
        if (findPackage(packageId)?.contact_sales) {
            return t('billing.plans.actions.contact_sales');
        }

        switch (getPackageAction(packageId)) {
            case 'current':
                return uiText('current_plan_text', toValue(options.billingConfig).ui.current_plan_text);
            case 'upgrade':
                return toValue(options.currentPackage) === 'free' ? t('billing.plans.actions.upgrade') : t('billing.plans.actions.upgrade_plan');
            case 'downgrade':
                return packageId === 'free' ? t('billing.plans.actions.downgrade_to_free') : t('billing.plans.actions.downgrade_plan');
            case 'same':
                return t('billing.plans.actions.switch_plan');
            default:
                return t('billing.plans.actions.select_plan');
        }
    };

    /** "Features included" for the first plan, "Everything in <previous plan>, plus" for the rest. */
    const getFeatureHeader = (packageId: string): string => {
        const packages = packagesArray();
        const packageIndex = packages.findIndex((pkg) => pkg.id === packageId);

        if (packageIndex === 0 || packageIndex === -1) {
            return t('billing.plans.features_included');
        }

        return t('billing.plans.everything_in', { plan: planName(packages[packageIndex - 1]) });
    };

    // A full-page redirect: the subscribe route sends the browser to Stripe Checkout.
    const subscribeToPlan = (packageId: string): void => {
        isSubscribing.value = true;
        subscribingPackage.value = packageId;
        window.location.href = route('subscribe', { package: packageId, yearly: toValue(options.isYearly) });
    };

    const changeSubscription = (packageId: string): void => {
        isSubscribing.value = true;
        subscribingPackage.value = packageId;

        const form = useForm({
            package: packageId,
            is_yearly: toValue(options.isYearly),
        });

        form.post(route('billing.subscription.change'), {
            onFinish: () => {
                isSubscribing.value = false;
                subscribingPackage.value = '';
            },
        });
    };

    const handlePackageSelection = (packageId: string): void => {
        // Contact-sales packages aren't self-serve: go to the brand's website.
        if (findPackage(packageId)?.contact_sales) {
            if (brand.value.website_url) {
                window.location.href = brand.value.website_url;
            }
            return;
        }

        if (getPackageAction(packageId) === 'current') {
            return;
        }

        if (page.props.subscribed) {
            changeSubscription(packageId);
        } else {
            subscribeToPlan(packageId);
        }
    };

    return { isSubscribing, subscribingPackage, isCurrentPackage, getButtonText, getFeatureHeader, handlePackageSelection };
}
