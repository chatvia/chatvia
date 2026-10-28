import { useI18n } from 'vue-i18n';

/**
 * Translate a config-sourced catalog string (connector names, descriptions, …)
 * by a stable key, falling back to the raw English the server sent when no
 * translation exists for the active locale.
 *
 * The catalog text itself lives in PHP config (the English source of truth) and
 * reaches the frontend as plain strings. Translations live in vue-i18n fragments
 * keyed by a stable identifier (slug). Because the `en` fragments mirror
 * config verbatim, English renders byte-identically; any missing or future string
 * gracefully shows the English fallback instead of a raw key.
 *
 *   const { catalogText } = useCatalogText();
 *   catalogText(`connectors.catalog.${slug}.name`, connector.name);
 */
export function useCatalogText(): { catalogText: (key: string, fallback: string) => string } {
    const { t, te } = useI18n();

    const catalogText = (key: string, fallback: string): string => (te(key) ? t(key) : fallback);

    return { catalogText };
}
