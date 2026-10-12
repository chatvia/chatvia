<?php

declare(strict_types=1);

namespace Domain\Conversation\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Which surface a conversation originated from. Set once at insert time and
 * read-only thereafter; drives chat-log filtering and analytics segmentation.
 *
 * An agent exposes several surfaces simultaneously — Hosted Chat (the standalone
 * chat page served at `/chat/{slug}`), Chat Widget (the embeddable floating-
 * bubble script), Command Bar (the embeddable command-palette launcher), and the
 * messaging channels (Telegram, Slack, Messenger, WhatsApp, Microsoft Teams,
 * Instagram). Playground previews reuse the same enum since they render the same
 * surfaces.
 */
#[TypeScript]
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

    /**
     * The channel's name as reports show it, e.g. "Chat Widget" or "WhatsApp".
     */
    public function label(): string
    {
        return match ($this) {
            self::HostedChat => 'Hosted Chat',
            self::Widget => 'Chat Widget',
            self::Bar => 'Command Bar',
            self::Telegram => 'Telegram',
            self::Slack => 'Slack',
            self::Messenger => 'Messenger',
            self::Whatsapp => 'WhatsApp',
            self::MsTeams => 'MS Teams',
            self::Instagram => 'Instagram',
        };
    }

    /**
     * Web surfaces render rich inline artifacts (charts, forms); messaging
     * channels can only show text, so artifact-emitting tools fall back there.
     */
    public function isWeb(): bool
    {
        return match ($this) {
            self::HostedChat, self::Widget, self::Bar => true,
            default => false,
        };
    }
}
