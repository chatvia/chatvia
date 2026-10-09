<?php

declare(strict_types=1);

namespace Tests\Support\Whatsapp;

use Domain\Channel\Whatsapp\Services\WhatsappClient;

/**
 * In-memory stand-in for {@see WhatsappClient} so tests never touch the Meta
 * Graph API. Records outbound sends/typing/subscriptions and lets a test
 * pretend the credentials are valid (`$number`) or rejected (`$number = null`).
 */
class FakeWhatsappClient extends WhatsappClient
{
    /** @var array{display_phone_number: ?string, verified_name: ?string}|null */
    public ?array $number = ['display_phone_number' => '+15551234567', 'verified_name' => 'Acme Support'];

    /** @var list<array{token: string, wabaId: string}> */
    public array $subscriptions = [];

    /** @var list<array{token: string, phoneNumberId: string, pin: string}> */
    public array $registrations = [];

    /** @var list<array{token: string, phoneNumberId: string, to: string, markdown: string}> */
    public array $sent = [];

    /** @var list<array{token: string, phoneNumberId: string, messageId: string}> */
    public array $typing = [];

    public function numberInfo(string $token, string $phoneNumberId): ?array
    {
        return $this->number;
    }

    public function subscribeWaba(string $token, string $wabaId): bool
    {
        $this->subscriptions[] = ['token' => $token, 'wabaId' => $wabaId];

        return true;
    }

    public function registerNumber(string $token, string $phoneNumberId, string $pin): bool
    {
        $this->registrations[] = ['token' => $token, 'phoneNumberId' => $phoneNumberId, 'pin' => $pin];

        return true;
    }

    public function sendMessage(string $token, string $phoneNumberId, string $to, string $markdown): void
    {
        $this->sent[] = ['token' => $token, 'phoneNumberId' => $phoneNumberId, 'to' => $to, 'markdown' => $markdown];
    }

    public function sendTypingIndicator(string $token, string $phoneNumberId, string $messageId): void
    {
        $this->typing[] = ['token' => $token, 'phoneNumberId' => $phoneNumberId, 'messageId' => $messageId];
    }
}
