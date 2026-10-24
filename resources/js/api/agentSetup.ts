/**
 * The JSON endpoints behind an agent's Setup page: the appearance text and its
 * per-language edits, conversation starters (save + AI suggestions), widget
 * chat bubbles, and the avatar / logo images of the appearance settings.
 *
 * The plain settings forms (colors, behaviour, languages, …) are Inertia form
 * visits and stay in the page; only the calls that need the JSON answer live here.
 */
import appearanceRoutes from '@/routes/agents/appearance';
import bubbleRoutes from '@/routes/agents/bubbles';
import settingsRoutes from '@/routes/agents/settings';
import starterRoutes from '@/routes/agents/starters';
import { del, post, put } from './http';

/** Text per field, per locale: `{ welcome_message: { de: '…', fr: '…' } }`. */
export type LocaleMap = Record<string, Record<string, string>>;

/** The appearance strings shown in every chat surface. */
export interface AppearanceText {
    chat_window_title: string;
    welcome_message: string;
    composer_placeholder: string;
    /** Optional short notice under the composer; empty shows none. */
    notice: string;
}

/** A conversation starter as stored: base text, icon, AI translations and manual edits. */
export interface StarterItem {
    id?: string | null;
    text: string;
    icon: string | null;
    translations?: Record<string, string>;
    overrides?: Record<string, string>;
}

/** A starter as sent on save. The server makes the translations. */
export interface StarterInput {
    id?: string | null;
    text: string;
    icon: string | null;
    overrides: Record<string, string>;
}

/** A widget chat-bubble prompt as stored: base text, AI translations and manual edits. */
export interface BubbleItem {
    text: string;
    translations?: Record<string, string>;
    overrides?: Record<string, string>;
}

/** A bubble as sent on save. The server makes the translations. */
export interface BubbleInput {
    text: string;
    overrides: Record<string, string>;
}

/** The two images of the appearance settings. */
export type AppearanceImage = 'avatar' | 'logo';

/**
 * Saves the appearance text and translates it into every supported language.
 * Answers with the fresh AI translations and the stored manual edits.
 */
export function saveAppearanceText(agentId: string, text: AppearanceText): Promise<{ translations: LocaleMap; overrides: LocaleMap }> {
    return post<{ translations: LocaleMap; overrides: LocaleMap }>(appearanceRoutes.text.url(agentId), text);
}

/** Saves the owner's manual per-language edits of the appearance text. */
export function saveAppearanceOverrides(agentId: string, overrides: LocaleMap): Promise<{ overrides: LocaleMap }> {
    return put<{ overrides: LocaleMap }>(appearanceRoutes.translations.update.url(agentId), { overrides });
}

/** Replaces the agent's starters. Answers with the stored starters and their translations. */
export function saveStarters(agentId: string, starters: StarterInput[]): Promise<{ starters: StarterItem[] }> {
    return put<{ starters: StarterItem[] }>(starterRoutes.update.url(agentId), { starters });
}

/** Asks the AI for `count` starter ideas based on the agent's knowledge base. Nothing is saved. */
export function generateStarters(agentId: string, count: number): Promise<{ suggestions: string[] }> {
    return post<{ suggestions: string[] }>(starterRoutes.generate.url(agentId), { count });
}

/** Replaces the widget's chat bubbles. Answers with the stored bubbles and their translations. */
export function saveBubbles(agentId: string, bubbles: BubbleInput[]): Promise<{ bubbles: BubbleItem[] }> {
    return put<{ bubbles: BubbleItem[] }>(bubbleRoutes.update.url(agentId), { bubbles });
}

/** Uploads a new avatar or logo image for the agent. */
export function uploadAppearanceImage(agentId: string, key: AppearanceImage, file: File): Promise<void> {
    const data = new FormData();
    data.append(key, file);

    return post(settingsRoutes.update.url({ agent: agentId, group: 'appearance' }), data);
}

/** Removes the agent's avatar or logo image. */
export function deleteAppearanceImage(agentId: string, key: AppearanceImage): Promise<void> {
    return del(settingsRoutes.delete.url({ agent: agentId, group: 'appearance' }), { data: { key } });
}
