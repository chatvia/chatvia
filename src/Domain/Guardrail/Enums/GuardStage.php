<?php

declare(strict_types=1);

namespace Domain\Guardrail\Enums;

/**
 * Where a guard ran in the turn: on the visitor's message (input), on a
 * retrieved RAG source chunk (source), on what a tool or MCP server returned
 * (tool), or on the agent's answer (output).
 */
enum GuardStage: string
{
    case Input = 'input';
    case Source = 'source';
    case Tool = 'tool';
    case Output = 'output';

    public function label(): string
    {
        return match ($this) {
            self::Input => 'Input',
            self::Source => 'Source',
            self::Tool => 'Tool',
            self::Output => 'Output',
        };
    }
}
