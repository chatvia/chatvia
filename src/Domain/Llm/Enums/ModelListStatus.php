<?php

declare(strict_types=1);

namespace Domain\Llm\Enums;

/**
 * How reading a provider's model list went: listed, the key was refused,
 * the server could not be reached, it answered with an error (some servers
 * have no list), or it listed no chat models.
 */
enum ModelListStatus: string
{
    case Listed = 'listed';
    case KeyRefused = 'key_refused';
    case Unreachable = 'unreachable';
    case Failed = 'failed';
    case Empty = 'empty';
}
