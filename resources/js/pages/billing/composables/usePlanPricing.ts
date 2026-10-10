import { computed, toValue, type MaybeRefOrGetter } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PlanBillingConfig, PlanPackage } from '../types';

/**
 * Price labels for the plan cards. Prices arrive in cents; yearly plans are
 * shown as a monthly equivalent plus the yearly total, and an active
 * site-wide discount (`config('billing.discount')`) is applied on top. The
 * free plan never gets a discount and contact-sales plans show no price.
 */
export function usePlanPricing(config: MaybeRefOrGetter<PlanBillingConfig>, isYearly: MaybeRefOrGetter<boolean>) {
    const { t, n } = useI18n();

    const isDiscountActive = computed(() => {
        const discount = toValue(config).discount;
        return discount.percentage > 0 && (!discount.ends_at || new Date(discount.ends_at) > new Date());
    });

    const money = (cents: number): string => `${toValue(config).currencySymbol}${(cents / 100).toFixed(2)}`;

    const applyDiscount = (cents: number): number => cents * (1 - toValue(config).discount.percentage / 100);

    /** The (discounted) monthly price, or "Custom" for contact-sales plans. */
    const getPackagePrice = (pkg: PlanPackage): string => {
        if (pkg.contact_sales) {
            return t('billing.plans.custom');
        }

        let priceCents = toValue(isYearly) && pkg.id !== 'free' ? pkg.yearly_price / 12 : pkg.monthly_price;

        if (isDiscountActive.value && pkg.id !== 'free') {
            priceCents = applyDiscount(priceCents);
        }

        return money(Math.round(priceCents));
    };

    /** The undiscounted monthly price to strike through, when a discount applies. */
    const getOriginalPrice = (pkg: PlanPackage): string | null => {
        if (pkg.id === 'free' || pkg.contact_sales || !isDiscountActive.value) return null;

        return money(toValue(isYearly) ? pkg.yearly_price / 12 : pkg.monthly_price);
    };

    const getPackagePeriod = (): string => t('billing.plans.per_month');

    /** The (discounted) amount billed once a year, on the yearly cycle only. */
    const getYearlyTotal = (pkg: PlanPackage): string | null => {
        if (!toValue(isYearly) || pkg.id === 'free' || pkg.contact_sales) return null;

        return money(isDiscountActive.value ? applyDiscount(pkg.yearly_price) : pkg.yearly_price);
    };

    /** "Save 20%" on the yearly cycle: what the plan's yearly price saves against twelve monthly payments. */
    const getSavingsText = (pkg: PlanPackage): string | null => {
        if (!toValue(isYearly) || pkg.contact_sales || pkg.monthly_savings.percentage <= 0) return null;
        return t('billing.plans.save_percent', { percent: n(pkg.monthly_savings.percentage) });
    };

    return { isDiscountActive, getPackagePrice, getOriginalPrice, getPackagePeriod, getYearlyTotal, getSavingsText };
}
