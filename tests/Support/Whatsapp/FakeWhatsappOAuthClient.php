<?php

declare(strict_types=1);

namespace Tests\Support\Whatsapp;

use Domain\Channel\Services\MetaOAuthClient;

/**
 * Stand-in for {@see MetaOAuthClient} in WhatsApp Embedded Signup: trades any
 * code for `$token` (`null` fakes a rejected code), so tests never call
 * Facebook.
 */
class FakeWhatsappOAuthClient extends MetaOAuthClient
{
    public ?string $token = 'EAAG-business-token';

    public function exchangeEmbeddedSignupCode(string $code): ?string
    {
        return $this->token;
    }
}
