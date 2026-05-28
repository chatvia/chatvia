<?php

namespace App\Http\Resources\Api\V1;

use Spatie\LaravelData\Data;
use Support\Enums\FunctionWebhookMethod;

/**
 * Where and how a function's webhook is called. Header values are never
 * returned; `has_headers` only signals whether any are configured.
 */
class FunctionWebhookData extends Data
{
    public function __construct(
        /**
         * URL the webhook request is sent to.
         */
        public string $url,
        /**
         * HTTP method used for the webhook request.
         */
        public FunctionWebhookMethod $method,
        /**
         * Whether custom headers are configured for the webhook.
         */
        public bool $has_headers,
    ) {}
}
