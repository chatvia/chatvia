import { Facebook, Globe, Instagram, MessageSquare, Phone, Search, Send, Slack, Users, type LucideIcon } from 'lucide-vue-next';

/**
 * Channel surface a conversation originated from. Mirrors the backend
 * Domain\Conversation\Enums\ConversationSource enum.
 */
export type ConversationSource = 'hosted_chat' | 'widget' | 'bar' | 'telegram' | 'slack' | 'messenger' | 'whatsapp' | 'msteams' | 'instagram';

/**
 * Single source of truth for the icon shown next to each channel, so the same
 * surface looks identical in the conversation sidebar and the details panel.
 * Labels live in i18n (`agents.chatlog.source.*`, via getSourceDisplayName) —
 * this map is icon-only.
 */
export const sourceIcons: Record<ConversationSource, LucideIcon> = {
    hosted_chat: MessageSquare,
    widget: Globe,
    bar: Search,
    telegram: Send,
    slack: Slack,
    messenger: Facebook,
    whatsapp: Phone,
    msteams: Users,
    instagram: Instagram,
};

/** Icon for a channel, or null for unknown/legacy sources (e.g. playground, api). */
export function getSourceIcon(source: string | null | undefined): LucideIcon | null {
    return source && source in sourceIcons ? sourceIcons[source as ConversationSource] : null;
}
