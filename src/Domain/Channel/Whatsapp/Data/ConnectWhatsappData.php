<?php

declare(strict_types=1);

namespace Domain\Channel\Whatsapp\Data;

use Spatie\LaravelData\Data;

/**
 * The payload a WhatsApp Embedded Signup popup hands back: the OAuth `code`
 * (exchanged server-side for a business token) plus the WhatsApp Business
 * Account id and phone number id the customer picked inside Meta's flow.
 */
class ConnectWhatsappData extends Data
{
    public function __construct(
        public string $code,
        public string $wabaId,
        public string $phoneNumberId,
    ) {}
}
