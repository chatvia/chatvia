<?php

declare(strict_types=1);

namespace Domain\Conversation\Enums;

use Illuminate\Support\Str;

/**
 * How a tool call in a voice call ended, for the tool chip in the call
 * view. The output's text is for the model, and the call's data channel is
 * the browser's only line to the call, so the outcome travels in the id of
 * the output item ({@see itemId()}). The call view matches these strings,
 * so they must not change.
 */
enum VoiceToolOutcome: string
{
    case Done = 'done';
    // It ran but did not work, was rate limited, or was not offered.
    case Failed = 'failed';
    // Waiting for the visitor's yes: nothing was done yet.
    case Held = 'held';
    // The turn will not go on, so it was not run.
    case Cancelled = 'cancelled';

    /** A fresh id for the output item that carries this outcome (the provider allows 32 characters). */
    public function itemId(): string
    {
        return 'tool_'.$this->value.'_'.Str::lower(Str::random(12));
    }
}
