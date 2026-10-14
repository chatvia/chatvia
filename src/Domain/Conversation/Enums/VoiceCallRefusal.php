<?php

declare(strict_types=1);

namespace Domain\Conversation\Enums;

/**
 * Why a voice call was not opened. The chat clients switch on these strings,
 * so they must not change. Each one carries the HTTP status and the message
 * shown to the visitor.
 */
enum VoiceCallRefusal: string
{
    // Voice is not set up on this install, or not turned on for the agent
    // or this surface.
    case NotAvailable = 'voice_not_available';
    case MessageLimitReached = 'message_limit_reached';
    case ChatterMessageLimitReached = 'chatter_message_limit_reached';
    case RateLimited = 'rate_limited';
    // A teammate has taken over the conversation.
    case HandedOff = 'handed_off';
    // Every voice worker is on a call.
    case Busy = 'voice_busy';
    // The provider refused or did not answer.
    case Failed = 'voice_failed';

    public function status(): int
    {
        return match ($this) {
            self::NotAvailable => 403,
            self::MessageLimitReached, self::ChatterMessageLimitReached, self::RateLimited => 429,
            self::HandedOff => 409,
            self::Busy => 503,
            self::Failed => 502,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::NotAvailable => __('Voice conversations are not available for this agent.'),
            self::MessageLimitReached => __('This agent has reached its monthly message limit. Please try again next month.'),
            self::ChatterMessageLimitReached => __('You have reached your monthly message limit.'),
            self::RateLimited => __('This agent is receiving too many messages right now. Please try again in a moment.'),
            self::HandedOff => __('A team member has joined this conversation. Please continue in the chat.'),
            self::Busy => __('Voice is busy right now. Please try again in a moment.'),
            self::Failed => __('The voice call could not start. Please try again.'),
        };
    }
}
