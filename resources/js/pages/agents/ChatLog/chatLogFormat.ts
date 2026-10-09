/**
 * Display helpers for the chat log: translated labels and badge colours for
 * sentiment, tone, confidence, feedback and channel, plus flag paths and
 * search highlighting. Pure functions, shared by every chat log component
 * through `useChatLog()`.
 */
import { i18n } from '@/i18n';

const t = i18n.global.t;

const NEUTRAL_BADGE = 'bg-gray-100 text-gray-800';

const SENTIMENT_BADGES: Record<string, string> = {
    positive: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    neutral: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300',
    negative: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
};

const TONE_BADGES: Record<string, string> = {
    neutral: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300',
    frustrated: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
    urgent: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
    confused: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
    satisfied: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
};

const TONE_EMOJIS: Record<string, string> = {
    neutral: '😐',
    frustrated: '😤',
    urgent: '⚡',
    confused: '😕',
    satisfied: '😊',
};

const SENTIMENTS = ['positive', 'neutral', 'negative'];
const TONES = ['neutral', 'frustrated', 'urgent', 'confused', 'satisfied'];
const CONFIDENCE_LEVELS = ['high', 'medium', 'low'];
const SOURCES = ['hosted_chat', 'widget', 'bar', 'telegram', 'slack', 'messenger', 'whatsapp', 'msteams', 'instagram', 'playground', 'api'];

/** Confidence at or above these values counts as high / medium. */
const HIGH_CONFIDENCE = 0.8;
const MEDIUM_CONFIDENCE = 0.65;

/** Translates a known enum value under `prefix`, else shows the raw value. */
function label(prefix: string, known: string[], value: string): string {
    return known.includes(value) ? t(`${prefix}.${value}`) : value;
}

export function getFlagPath(countryCode: string | null | undefined): string | null {
    return countryCode ? `/images/flags/${countryCode.toLowerCase()}.svg` : null;
}

export function getSentimentDisplayName(sentiment: string): string {
    return label('agents.chatlog.sentiment', SENTIMENTS, sentiment);
}

export function getSentimentBadgeColor(sentiment: string): string {
    return SENTIMENT_BADGES[sentiment] || NEUTRAL_BADGE;
}

export function getToneDisplayName(tone: string): string {
    return label('agents.chatlog.tone', TONES, tone);
}

export function getToneEmoji(tone: string): string {
    return TONE_EMOJIS[tone] || TONE_EMOJIS.neutral;
}

export function getToneBadgeColor(tone: string): string {
    return TONE_BADGES[tone] || NEUTRAL_BADGE;
}

export function getConfidenceColor(score: number): string {
    if (score >= HIGH_CONFIDENCE) return 'text-green-600 dark:text-green-400';
    if (score >= MEDIUM_CONFIDENCE) return 'text-yellow-600 dark:text-yellow-400';

    return 'text-red-600 dark:text-red-400';
}

export function getConfidenceScoreDisplayName(score: string): string {
    return label('agents.chatlog.confidence', CONFIDENCE_LEVELS, score);
}

export function getFeedbackDisplayName(feedback: string): string {
    if (feedback === 'positive') return t('agents.chatlog.feedback.thumbs_up');
    if (feedback === 'negative') return t('agents.chatlog.feedback.thumbs_down');

    return feedback;
}

export function getSourceDisplayName(source: string): string {
    return label('agents.chatlog.source', SOURCES, source);
}

/** Point colour on the pulse chart: green for good, amber for neutral, red for bad moods. */
export function getPulsePointColor(score: number): string {
    if (score >= 0.5) return 'rgb(34, 197, 94)';
    if (score >= -0.5) return 'rgb(234, 179, 8)';

    return 'rgb(239, 68, 68)';
}

function escapeHtml(value: string): string {
    return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

/**
 * Marks search hits in a message for v-html. The text is visitor- or
 * assistant-written, so it MUST be HTML-escaped before the <mark> tags go in;
 * otherwise a message like `<img onerror=…>` would run in the operator's session.
 */
export function highlightText(text: string, query: string): string {
    const safe = escapeHtml(text);
    if (!query.trim()) return safe;

    const pattern = new RegExp(`(${escapeHtml(query).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');

    return safe.replace(pattern, '<mark class="bg-yellow-200 dark:bg-yellow-900/50 rounded px-0.5">$1</mark>');
}
