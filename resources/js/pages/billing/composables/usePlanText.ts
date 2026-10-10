import { useBrand } from '@/composables/useBrand';
import { useCatalogText } from '@/composables/useCatalogText';
import { useI18n } from 'vue-i18n';

type PlanFeature = Domain.Billing.Data.Resources.Package.PlanFeatureData;

/**
 * Translated plan texts for the billing pages. Plan names and descriptions
 * come from the plan in English; this overlays the translation by plan id
 * and falls back to the English the server sent. Feature bullets arrive as
 * templates (`billing.features.<key>` plus numbers) and are rendered in the
 * active language with its number format; lines typed on the plan are shown
 * as typed.
 */
export function usePlanText() {
    const { t, n } = useI18n();
    const { catalogText } = useCatalogText();
    const { brand } = useBrand();

    const planName = (pkg: { id: string; name: string }): string => catalogText(`billing.catalog.packages.${pkg.id}.name`, pkg.name);

    const packageDescription = (pkg: { id: string; description: string }): string =>
        catalogText(`billing.catalog.packages.${pkg.id}.description`, pkg.description);

    /** One feature bullet of a plan, in the active language. `count` picks the singular or plural form. */
    const featureText = (feature: PlanFeature): string => {
        if (feature.key === null) {
            return feature.text ?? '';
        }

        const params: Record<string, string> = { brand: brand.value.powered_by.label ?? brand.value.name };

        for (const [name, value] of Object.entries(feature.params)) {
            params[name] = typeof value === 'number' ? n(value) : value;
        }

        const key = `billing.features.${feature.key}`;
        const count = feature.params.count;

        return typeof count === 'number' ? t(key, params, count) : t(key, params);
    };

    return { planName, packageDescription, featureText };
}
