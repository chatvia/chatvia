import { useCatalogText } from '@/dashboard/composables/useCatalogText';

interface ConnectorLike {
    slug: string;
    name: string;
    description?: string | null;
    category?: string | null;
    tokenLabel?: string | null;
    tokenPlaceholder?: string | null;
}

// Map a config category label ("Developer Tools") to its translation-key slug
// ("developer_tools"). Mirrors connectors.categories.* in the i18n fragments.
const categorySlug = (category: string): string => category.toLowerCase().replace(/\s+/g, '_');

/**
 * Translate a connector's config-sourced strings (name/description/category/token
 * labels) by `slug`, falling back to the raw English the server sent. Connector
 * catalog text lives in `config/connectors.php` (English source of truth) and is
 * overlaid by the `connectors.*` i18n fragments — see {@link useCatalogText}.
 */
export function useConnectorText() {
    const { catalogText } = useCatalogText();

    const connectorName = (c: ConnectorLike): string => catalogText(`connectors.catalog.${c.slug}.name`, c.name);

    const connectorDescription = (c: ConnectorLike): string => catalogText(`connectors.catalog.${c.slug}.description`, c.description ?? '');

    const connectorCategory = (category: string): string => catalogText(`connectors.categories.${categorySlug(category)}`, category);

    const connectorTokenLabel = (c: ConnectorLike): string | null =>
        c.tokenLabel ? catalogText(`connectors.catalog.${c.slug}.token_label`, c.tokenLabel) : null;

    const connectorTokenPlaceholder = (c: ConnectorLike): string | null =>
        c.tokenPlaceholder ? catalogText(`connectors.catalog.${c.slug}.token_placeholder`, c.tokenPlaceholder) : null;

    return { connectorName, connectorDescription, connectorCategory, connectorTokenLabel, connectorTokenPlaceholder };
}
