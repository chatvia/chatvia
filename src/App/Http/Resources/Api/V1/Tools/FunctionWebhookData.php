<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Tools;

use Domain\Integration\Function\Enums\FunctionWebhookMethod;
use Spatie\LaravelData\Data;

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
