<?php

namespace Domain\Widget\Data\Auth;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The widget session token a protected agent's widget sends as X-AUTH-TOKEN
 * after a successful auth exchange, with its expiry (ISO 8601).
 */
#[TypeScript]
class WidgetAuthTokenData extends Data
{
    public function __construct(
        public string $auth_token,
        public string $expires_at,
    ) {}
}
