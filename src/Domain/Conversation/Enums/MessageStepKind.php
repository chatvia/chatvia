<?php

declare(strict_types=1);

namespace Domain\Conversation\Enums;

/**
 * One link of the step chain above an answer: a line the model wrote before
 * calling a tool, or a tool call it made. Stored in
 * `chat_messages.metadata.steps`.
 */
enum MessageStepKind: string
{
    case Text = 'text';
    case Tool = 'tool';
}
