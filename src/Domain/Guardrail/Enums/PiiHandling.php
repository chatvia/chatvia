<?php

declare(strict_types=1);

namespace Domain\Guardrail\Enums;

/**
 * What happens to a visitor's message that contains a kind of personal data:
 * mask it (the turn goes on with a placeholder) or block the message and ask
 * the visitor not to share it. Answers are always masked, never blocked.
 */
enum PiiHandling: string
{
    case Mask = 'mask';
    case Block = 'block';
}
