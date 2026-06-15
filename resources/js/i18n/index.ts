import { createI18n } from 'vue-i18n';
import en from './locales/en.json';

// Dashboard UI languages. Kept in lock-step with the public site
// (config/laravellocalization.php `supportedLocales` / `localesOrder`). Every code
// here has a ./locales/{code}.json bundle; `ar`/`fa` are right-to-left (see
// RTL_LOCALES). Agent response languages (config('chatvia.languages')) are a
// separate, larger list — don't conflate. Remove a code here to hide a UI language.
export const SUPPORTED_LOCALES = ['en', 'de', 'fr', 'ar', 'da', 'es', 'fa', 'fi', 'no', 'pl', 'pt', 'sv'] as const;
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
    { code: 'ar', nativeName: 'العربية', flag: 'sa' },
    { code: 'da', nativeName: 'Dansk', flag: 'dk' },
    { code: 'es', nativeName: 'Español', flag: 'es' },
    { code: 'fa', nativeName: 'فارسی', flag: 'ir' },
    { code: 'fi', nativeName: 'Suomi', flag: 'fi' },
    { code: 'no', nativeName: 'Norsk', flag: 'no' },
    { code: 'pl', nativeName: 'Polski', flag: 'pl' },
    { code: 'pt', nativeName: 'Português', flag: 'pt' },
    { code: 'sv', nativeName: 'Svenska', flag: 'se' },
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

export function resolveLocale(preference?: string | null): SupportedLocale {
    // A signed-in user's saved locale (anything but 'auto') wins. Otherwise fall
    // back to the remembered guest choice, then the browser language.
    if (preference && preference !== 'auto') return normalizeLocale(preference);
    const stored = getStoredLocale();
    if (stored) return stored;
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
