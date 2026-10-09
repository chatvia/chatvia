import { useCatalogText } from '@/composables/useCatalogText';

/**
 * Translated plan texts for the billing pages. Plan names, descriptions,
 * feature bullets and pricing-page labels come from `config/billing.php` in
 * English; this overlays the translation by stable key (plan id, feature key)
 * and falls back to the English the server sent.
 */
export function usePlanText() {
    const { catalogText } = useCatalogText();

    const planName = (pkg: { id: string; name: string }): string => catalogText(`billing.catalog.packages.${pkg.id}.name`, pkg.name);

    const packageDescription = (pkg: { id: string; description: string }): string =>
        catalogText(`billing.catalog.packages.${pkg.id}.description`, pkg.description);

    /** A feature bullet of a plan, keyed by plan id + billing cycle + feature key. */
    const featureLabel = (planId: string | undefined, yearly: boolean, key: string, text: string): string =>
        catalogText(`billing.catalog.packages.${planId}.features.${yearly ? 'yearly' : 'monthly'}.${key}`, text);

    /** A pricing-page label from `config('billing.ui')`. */
    const uiText = (key: string, fallback: string): string => catalogText(`billing.catalog.ui.${key}`, fallback);

    return { planName, packageDescription, featureLabel, uiText };
}
