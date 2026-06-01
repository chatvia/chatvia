<?php

namespace Support\Enums\Functions;

/**
 * How Chatvia authenticates a function's outbound webhook call.
 */
enum FunctionAuthType: string
{
    case None = 'none';
    case Bearer = 'bearer';
    case Header = 'header';
    case Hmac = 'hmac';
}
