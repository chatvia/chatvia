import { createI18n } from 'vue-i18n';
import en from './locales/en.json';

export const SUPPORTED_LOCALES = ['en', 'ar', 'da', 'de', 'es', 'fa', 'fr', 'hi', 'id', 'ja', 'ko', 'pt', 'ru', 'tr', 'vi', 'zh'] as const;
export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number];

// `ar` (Arabic), `fa` (Farsi/Persian), `he` (Hebrew), `ur` (Urdu) all use the
// Arabic script and require right-to-left layout — listed even if not yet in
// SUPPORTED_LOCALES so the dir flip kicks in if the locale ever shows up.
export const RTL_LOCALES: ReadonlySet<string> = new Set(['ar', 'he', 'fa', 'ur']);

export interface LocaleMeta {
    code: SupportedLocale;
    // Endonym — shown in its own script so users recognise their language.
    nativeName: string;
    // Filename (without extension) under `/public/images/flags/`.
    flag: string;
}

// Single source of truth for the language switcher. `flag` maps the locale to a
// representative country flag SVG (no per-language flags exist).
export const LOCALES: readonly LocaleMeta[] = [
    { code: 'en', nativeName: 'English', flag: 'gb' },
    { code: 'ar', nativeName: 'العربية', flag: 'iq' },
    { code: 'da', nativeName: 'Dansk', flag: 'dk' },
    { code: 'de', nativeName: 'Deutsch', flag: 'de' },
    { code: 'es', nativeName: 'Español', flag: 'es' },
    { code: 'fa', nativeName: 'فارسی', flag: 'ir' },
    { code: 'fr', nativeName: 'Français', flag: 'fr' },
    { code: 'hi', nativeName: 'हिन्दी', flag: 'in' },
    { code: 'id', nativeName: 'Bahasa Indonesia', flag: 'id' },
    { code: 'ja', nativeName: '日本語', flag: 'jp' },
    { code: 'ko', nativeName: '한국어', flag: 'kr' },
    { code: 'pt', nativeName: 'Português', flag: 'pt' },
    { code: 'ru', nativeName: 'Русский', flag: 'ru' },
    { code: 'tr', nativeName: 'Türkçe', flag: 'tr' },
    { code: 'vi', nativeName: 'Tiếng Việt', flag: 'vn' },
    { code: 'zh', nativeName: '中文', flag: 'cn' },
];

// The English bundle is the canonical schema every other locale conforms to.
type MessageSchema = typeof en;

// Lazy importers for every non-default locale. Vite code-splits each JSON into
// its own chunk so only the active language is fetched over the wire.
const localeLoaders = import.meta.glob<{ default: MessageSchema }>('./locales/*.json');

const loadedLocales = new Set<SupportedLocale>(['en']);

export function normalizeLocale(input?: string | null): SupportedLocale {
    if (!input) return 'en';
    const code = input.trim().toLowerCase().split(/[-_]/)[0];
    return (SUPPORTED_LOCALES as readonly string[]).includes(code) ? (code as SupportedLocale) : 'en';
}

export function resolveLocale(preference?: string | null): SupportedLocale {
    if (preference && preference !== 'auto') return normalizeLocale(preference);
    if (typeof navigator !== 'undefined') {
        return normalizeLocale(navigator.language || (navigator as any).userLanguage);
    }
    return 'en';
}

export function isRTL(locale: string): boolean {
    return RTL_LOCALES.has(locale);
}

export const i18n = createI18n({
    legacy: false,
    locale: 'en',
    fallbackLocale: 'en',
    messages: { en },
    missingWarn: false,
    fallbackWarn: false,
});

// vue-i18n infers its locale union + message schema from the `en` bundle above
// (the only one eagerly loaded). The lazily-loaded locales share that schema
// but widen the locale to SupportedLocale, so the runtime setters are narrowed
// back to the inferred type here.
type EagerLocale = typeof i18n.global.locale.value;

// Fetch and register a locale's messages on demand. No-op for `en` (eager) and
// for locales already loaded — the importer only runs once per language.
async function loadLocaleMessages(locale: SupportedLocale): Promise<void> {
    if (loadedLocales.has(locale)) return;

    const loader = localeLoaders[`./locales/${locale}.json`];
    if (!loader) return;

    const module = await loader();
    i18n.global.setLocaleMessage(locale as EagerLocale, module.default);
    loadedLocales.add(locale);
}

export async function setI18nLocale(locale: string): Promise<void> {
    const normalized = normalizeLocale(locale);
    await loadLocaleMessages(normalized);
    i18n.global.locale.value = normalized as EagerLocale;
    if (typeof document !== 'undefined') {
        document.documentElement.lang = normalized;
        document.documentElement.dir = isRTL(normalized) ? 'rtl' : 'ltr';
    }
}
