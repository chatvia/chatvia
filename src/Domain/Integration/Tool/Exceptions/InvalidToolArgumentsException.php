<?php

declare(strict_types=1);

namespace Domain\Integration\Tool\Exceptions;

use Domain\Integration\Tool\Dispatch\ToolArgumentValidator;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * The model's arguments for a tool call do not fit the tool's parameters
 * ({@see ToolArgumentValidator}). The message lists what is wrong in words
 * the model can act on; it holds key names only, never argument values.
 */
final class InvalidToolArgumentsException extends RuntimeException
{
    /**
     * @param  list<string>  $problems
     */
    public static function because(array $problems): self
    {
        return new self('the arguments do not match the tool\'s parameters: '.Arr::join($problems, '; ').'. Fix the arguments and call the tool again.');
    }
}
