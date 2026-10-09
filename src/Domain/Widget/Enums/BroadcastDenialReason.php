<?php

namespace Domain\Widget\Enums;

/**
 * Why a widget visitor may not join a conversation's presence channel. The
 * value is the error text the widget API answers with.
 */
enum BroadcastDenialReason: string
{
    /** The channel is not a conversation presence channel (`presence-chat.<id>`). */
    case InvalidChannel = 'Invalid channel';

    /** No conversation with that id belongs to the visitor's session on this agent. */
    case UnknownConversation = 'Unauthorized';
}
