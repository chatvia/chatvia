import { createI18n } from 'vue-i18n';
import en from './locales/en.json';

// Dashboard UI languages, ordered EU-first: English, the EU majors, the EU/EEA
// Nordic + Polish block, RTL (Arabic, Persian), then the rest. Every code here
// has a ./locales/{code}.json bundle and MUST also appear in
// config('chatvia.languages') (UpdatePreferencesRequest validates a user's saved
// locale against that list, and SetLocale honours it). `ar`/`fa` are right-to-left
// (see RTL_LOCALES). Remove a code from both lists to hide a UI language.
export const SUPPORTED_LOCALES = [
    'en',
    'de',
    'fr',
    'es',
    'pt',
    'pl',
    'da',
    'sv',
    'fi',
    'no',
    'ar',
    'fa',
    'hi',
    'id',
    'ja',
    'ko',
    'ru',
    'tr',
    'vi',
    'zh',
] as const;
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
    { code: 'de', nativeName: 'Deutsch', flag: 'de' },
    { code: 'fr', nativeName: 'Français', flag: 'fr' },
    { code: 'es', nativeName: 'Español', flag: 'es' },
    { code: 'pt', nativeName: 'Português', flag: 'pt' },
    { code: 'pl', nativeName: 'Polski', flag: 'pl' },
    { code: 'da', nativeName: 'Dansk', flag: 'dk' },
    { code: 'sv', nativeName: 'Svenska', flag: 'se' },
    { code: 'fi', nativeName: 'Suomi', flag: 'fi' },
    { code: 'no', nativeName: 'Norsk', flag: 'no' },
    { code: 'ar', nativeName: 'العربية', flag: 'iq' },
    { code: 'fa', nativeName: 'فارسی', flag: 'ir' },
    { code: 'hi', nativeName: 'हिन्दी', flag: 'in' },
    { code: 'id', nativeName: 'Bahasa Indonesia', flag: 'id' },
    { code: 'ja', nativeName: '日本語', flag: 'jp' },
    { code: 'ko', nativeName: '한국어', flag: 'kr' },
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

// Guests have no `users.locale`, so their language choice (e.g. on the login
// page) is remembered client-side here and consulted before the browser default.
const LOCALE_STORAGE_KEY = 'chatvia.locale';

function getStoredLocale(): SupportedLocale | null {
    if (typeof localStorage === 'undefined') return null;
    try {
        const v = localStorage.getItem(LOCALE_STORAGE_KEY);
        return v && (SUPPORTED_LOCALES as readonly string[]).includes(v) ? (v as SupportedLocale) : null;
    } catch {
        return null;
    }
}

/** Remember an explicit language choice so it survives navigation + login. */
export function persistLocale(code: string): void {
    if (typeof localStorage === 'undefined') return;
    try {
        localStorage.setItem(LOCALE_STORAGE_KEY, normalizeLocale(code));
    } catch {
        // Storage unavailable (private mode / quota) — choice stays in-memory only.
    }
}

/** The supported locale for a language code, or null when it has none. */
function supportedOrNull(input?: string | null): SupportedLocale | null {
    if (!input) return null;
    const code = input.trim().toLowerCase().split(/[-_]/)[0];
    return (SUPPORTED_LOCALES as readonly string[]).includes(code) ? (code as SupportedLocale) : null;
}

export function resolveLocale(preference?: string | null, defaultLocale?: string | null): SupportedLocale {
    // A signed-in user's saved locale (anything but 'auto') wins. Otherwise fall
    // back to the remembered guest choice, then the browser language when it
    // is supported, then the install's default language (Settings → Platform).
    if (preference && preference !== 'auto') return normalizeLocale(preference);
    const stored = getStoredLocale();
    if (stored) return stored;
    if (typeof navigator !== 'undefined') {
        // `userLanguage` is the legacy IE/old-Edge name for `language`.
        const legacy = (navigator as Navigator & { userLanguage?: string }).userLanguage;
        const browser = supportedOrNull(navigator.language || legacy);
        if (browser) return browser;
    }
    return normalizeLocale(defaultLocale);
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
