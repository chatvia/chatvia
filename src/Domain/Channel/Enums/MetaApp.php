<?php

declare(strict_types=1);

namespace Domain\Channel\Enums;

/**
 * The Meta apps behind the Messenger, Instagram and WhatsApp channels. Each
 * channel has its own app: App ID and secret, webhook verify token and Facebook
 * Login for Business configuration, set in the admin panel (Settings →
 * Channels). An install may enter the same app for all three.
 *
 * A channel's OAuth, its webhook signature check and its verify challenge must
 * all use the same app, because Meta signs with the secret of the app that owns
 * the webhook subscription.
 */
enum MetaApp: string
{
    case Messenger = 'messenger';
    case Instagram = 'instagram';
    case Whatsapp = 'whatsapp';

    public function appId(): string
    {
        return (string) $this->config('app_id');
    }

    public function appSecret(): string
    {
        return (string) $this->config('app_secret');
    }

    public function webhookVerifyToken(): ?string
    {
        return $this->filled('webhook_verify_token');
    }

    /** The Login-for-Business configuration, or null for a classic scope request. */
    public function configId(): ?string
    {
        return $this->filled('config_id');
    }

    /** The OAuth callback URL. WhatsApp's Embedded Signup has none. */
    public function redirectUri(): ?string
    {
        return $this->filled('redirect');
    }

    /** The PIN used to register connected WhatsApp numbers (WhatsApp only). */
    public function registerPin(): ?string
    {
        return $this->filled('register_pin');
    }

    private function filled(string $key): ?string
    {
        $value = $this->config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function config(string $key): mixed
    {
        return config("services.meta.{$this->value}.{$key}");
    }
}
