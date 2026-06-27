type LocaleMap = Record<string, Record<string, string>>;

export interface AppearanceTextStrings {
    chat_window_title: string | null;
    welcome_message: string | null;
    composer_placeholder: string | null;
}

/**
 * Resolve the agent's three appearance strings for a visitor locale, mirroring
 * the server-side AppearanceTextResolver: manual override > AI translation >
 * base value. The hosted chat surfaces use this so the in-header language
 * switcher re-localizes the title / welcome / composer placeholder instantly,
 * with no round-trip (every supported language is already in the payload).
 */
export function resolveAppearanceText(appearance: Record<string, unknown> | undefined | null, locale: string): AppearanceTextStrings {
    const a = (appearance ?? {}) as Record<string, unknown>;
    const translations = (a.text_translations ?? {}) as LocaleMap;
    const overrides = (a.text_overrides ?? {}) as LocaleMap;

    const pick = (key: string): string | null => {
        const override = overrides[key]?.[locale];
        if (typeof override === 'string' && override !== '') return override;

        const translated = translations[key]?.[locale];
        if (typeof translated === 'string' && translated !== '') return translated;

        const base = a[key];
        return typeof base === 'string' ? base : null;
    };

    return {
        chat_window_title: pick('chat_window_title'),
        welcome_message: pick('welcome_message'),
        composer_placeholder: pick('composer_placeholder'),
    };
}

interface StarterItem {
    text: string;
    icon?: string | null;
    translations?: Record<string, string>;
    overrides?: Record<string, string>;
}

/**
 * Resolve a list of conversation starters for a visitor locale — same
 * precedence as the strings above (override > translation > base). Returns the
 * lean { text, icon } shape the chat surfaces render. Lets the hosted chat
 * re-localize starters instantly when the visitor switches language.
 */
export function resolveStarters(items: StarterItem[] | undefined | null, locale: string): { text: string; icon: string | null }[] {
    return (Array.isArray(items) ? items : []).map((item) => {
        const override = item.overrides?.[locale];
        const translated = item.translations?.[locale];
        const text =
            typeof override === 'string' && override !== ''
                ? override
                : typeof translated === 'string' && translated !== ''
                  ? translated
                  : (item.text ?? '');
        return { text, icon: item.icon ?? null };
    });
}
