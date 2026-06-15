// Single source of truth for the 20 dashboard locales, in EU-first order.
// `eu` = official EU language; `no` is EEA (European, grouped with EU block).
// Order: English, EU majors, EU/EEA Nordic + Polish, RTL, then the rest.
export const LANGS = [
    { code: 'en', name: 'English', native: 'English', flag: 'gb', rtl: false, eu: true },
    { code: 'de', name: 'German', native: 'Deutsch', flag: 'de', rtl: false, eu: true },
    { code: 'fr', name: 'French', native: 'Français', flag: 'fr', rtl: false, eu: true },
    { code: 'es', name: 'Spanish', native: 'Español', flag: 'es', rtl: false, eu: true },
    { code: 'pt', name: 'Portuguese', native: 'Português', flag: 'pt', rtl: false, eu: true },
    { code: 'pl', name: 'Polish', native: 'Polski', flag: 'pl', rtl: false, eu: true },
    { code: 'da', name: 'Danish', native: 'Dansk', flag: 'dk', rtl: false, eu: true },
    { code: 'sv', name: 'Swedish', native: 'Svenska', flag: 'se', rtl: false, eu: true },
    { code: 'fi', name: 'Finnish', native: 'Suomi', flag: 'fi', rtl: false, eu: true },
    { code: 'no', name: 'Norwegian', native: 'Norsk', flag: 'no', rtl: false, eu: false },
    { code: 'ar', name: 'Arabic', native: 'العربية', flag: 'sa', rtl: true, eu: false },
    { code: 'fa', name: 'Persian (Farsi)', native: 'فارسی', flag: 'ir', rtl: true, eu: false },
    { code: 'hi', name: 'Hindi', native: 'हिन्दी', flag: 'in', rtl: false, eu: false },
    { code: 'id', name: 'Indonesian', native: 'Bahasa Indonesia', flag: 'id', rtl: false, eu: false },
    { code: 'ja', name: 'Japanese', native: '日本語', flag: 'jp', rtl: false, eu: false },
    { code: 'ko', name: 'Korean', native: '한국어', flag: 'kr', rtl: false, eu: false },
    { code: 'ru', name: 'Russian', native: 'Русский', flag: 'ru', rtl: false, eu: false },
    { code: 'tr', name: 'Turkish', native: 'Türkçe', flag: 'tr', rtl: false, eu: false },
    { code: 'vi', name: 'Vietnamese', native: 'Tiếng Việt', flag: 'vn', rtl: false, eu: false },
    { code: 'zh', name: 'Chinese (Simplified)', native: '中文', flag: 'cn', rtl: false, eu: false },
];

export const localesDir = '/Users/ahmad/Sites/chatvia/resources/js/i18n/locales';
export const workDir = '/tmp/chatvia-i18n';

export function flatten(obj, prefix = '', out = {}) {
    for (const [k, v] of Object.entries(obj)) {
        const key = prefix ? `${prefix}.${k}` : k;
        if (v !== null && typeof v === 'object' && !Array.isArray(v)) flatten(v, key, out);
        else out[key] = v;
    }
    return out;
}

// A value carries no translatable words once interpolation placeholders are
// stripped (e.g. "{count}", "{'@'}{username}", "{a} {b}"). Such keys are copied
// from English verbatim rather than sent to a translator.
export function isVerbatim(value) {
    if (typeof value !== 'string') return true;
    const stripped = value.replace(/\{[^}]*\}/g, '');
    return !/\p{L}/u.test(stripped);
}
