<?php

declare(strict_types=1);

namespace Domain\Conversation\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Where a stored message came from (`messages.source`): one of the agent's
 * surfaces ({@see ConversationSource}), a playground preview, or a visitor
 * message relayed while a staff member had taken the conversation over.
 * Named "origin" because MessageSource is the model for the knowledge rows
 * under an answer.
 */
#[TypeScript]
enum MessageOrigin: string
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
    case Api = 'api';
    case Playground = 'playground';
    case Handoff = 'handoff';

    /** The origin of an ordinary message on a surface. */
    public static function fromConversationSource(ConversationSource $source): self
    {
        return self::from($source->value);
    }
}
