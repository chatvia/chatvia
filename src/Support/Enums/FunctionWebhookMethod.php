<?php

namespace Support\Enums;

/**
 * HTTP method Chatvia uses when calling a function's webhook.
 */
enum FunctionWebhookMethod: string
{
    case Post = 'POST';
    case Get = 'GET';
    case Patch = 'PATCH';
    case Delete = 'DELETE';
}
