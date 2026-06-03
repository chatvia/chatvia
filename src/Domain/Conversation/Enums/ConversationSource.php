<?php

namespace Domain\Conversation\Enums;

/**
 * Which surface a conversation originated from. Set once at insert time and
 * read-only thereafter; drives chat-log filtering and analytics segmentation.
 *
 * An agent exposes three distinct surfaces simultaneously — Hosted Chat (the
 * standalone chat page served at `{sub_domain}.app`), Widget (the embeddable
 * floating-bubble script), and Bar (the embeddable search bar). Playground
 * previews reuse the same enum since they render the same surfaces.
 */
enum ConversationSource: string
{
    case HostedChat = 'hosted_chat';
    case Widget = 'widget';
    case Bar = 'bar';
}
