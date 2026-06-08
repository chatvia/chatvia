<?php

namespace Domain\Guardrail\Enums;

/**
 * Where a guard ran in the turn: on the visitor's message (input), on a
 * retrieved RAG source chunk (source), or on the agent's answer (output).
 */
enum GuardStage: string
{
    case Input = 'input';
    case Source = 'source';
    case Output = 'output';

    public function label(): string
    {
        return match ($this) {
            self::Input => 'Input',
            self::Source => 'Source',
            self::Output => 'Output',
        };
    }
}
