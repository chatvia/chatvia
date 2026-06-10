<?php

namespace Domain\Conversation\Enums;

/**
 * Which surface a conversation originated from. Set once at insert time and
 * read-only thereafter; drives chat-log filtering and analytics segmentation.
 *
 * An agent exposes several surfaces simultaneously — Hosted Chat (the standalone
 * chat page served at `{sub_domain}.app`), Widget (the embeddable floating-bubble
 * script), Bar (the embeddable search bar), and the messaging channels (Telegram,
 * Slack, Messenger, WhatsApp, Microsoft Teams, Instagram). Playground previews
 * reuse the same enum since they render the same surfaces.
 */
enum ConversationSource: string
{
    case HostedChat = 'hosted_chat';
    case Widget = 'widget';
    case Bar = 'bar';
    case Telegram = 'telegram';
    case Slack = 'slack';
    case Messenger = 'messenger';
    case Whatsapp = 'whatsapp';
    case MsTeams = 'msteams';
    case Instagram = 'instagram';
}
