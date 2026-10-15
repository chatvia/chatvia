<?php

declare(strict_types=1);

namespace Domain\Guardrail\Enums;

/**
 * What the agent does with a message about a blocked topic: refuse with the
 * agent's refusal message, reply with the topic's own message, or hand the
 * conversation to a person (Chat Widget only; elsewhere it refuses).
 */
enum TopicResponse: string
{
    case Refuse = 'refuse';
    case Reply = 'reply';
    case Handoff = 'handoff';
}
