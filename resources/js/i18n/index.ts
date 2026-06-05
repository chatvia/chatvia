import { createI18n } from 'vue-i18n';
import ar from './locales/ar.json';
import da from './locales/da.json';
import de from './locales/de.json';
import en from './locales/en.json';
import es from './locales/es.json';
import fa from './locales/fa.json';
import fr from './locales/fr.json';
import hi from './locales/hi.json';
import id from './locales/id.json';
import ja from './locales/ja.json';
import ko from './locales/ko.json';
import pt from './locales/pt.json';
import ru from './locales/ru.json';
import tr from './locales/tr.json';
import vi from './locales/vi.json';
import zh from './locales/zh.json';

export const SUPPORTED_LOCALES = ['en', 'ar', 'da', 'de', 'es', 'fa', 'fr', 'hi', 'id', 'ja', 'ko', 'pt', 'ru', 'tr', 'vi', 'zh'] as const;
export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number];

// `ar` (Arabic), `fa` (Farsi/Persian), `he` (Hebrew), `ur` (Urdu) all use the
// Arabic script and require right-to-left layout — listed even if not yet in
// SUPPORTED_LOCALES so the dir flip kicks in if the locale ever shows up.
export const RTL_LOCALES: ReadonlySet<string> = new Set(['ar', 'he', 'fa', 'ur']);

const messages = { en, ar, da, de, es, fa, fr, hi, id, ja, ko, pt, ru, tr, vi, zh } as const;

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
    messages,
    missingWarn: false,
    fallbackWarn: false,
});

export function setI18nLocale(locale: string): void {
    const normalized = normalizeLocale(locale);
    i18n.global.locale.value = normalized;
    if (typeof document !== 'undefined') {
        document.documentElement.lang = normalized;
        document.documentElement.dir = isRTL(normalized) ? 'rtl' : 'ltr';
    }
}
