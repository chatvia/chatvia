<?php

declare(strict_types=1);

namespace App\Http\Auth;

use RyanChandler\LaravelCloudflareTurnstile\Rules\Turnstile;

/**
 * The bot check of the public forms: Cloudflare Turnstile, once its site key
 * and secret key are saved in Settings → Security, on the forms switched on
 * there ({@see BotCheckForm}). The page renders the widget, which adds a
 * one-use token to the form (`cf-turnstile-response`); the request rules
 * check that token with Cloudflare.
 */
final class BotCheck
{
    /** The form field the Turnstile widget fills. */
    public const string FIELD = 'cf-turnstile-response';

    public function isConfigured(): bool
    {
        return filled(config('services.turnstile.key')) && filled(config('services.turnstile.secret'));
    }

    public function protects(BotCheckForm $form): bool
    {
        return $this->isConfigured() && (bool) config("chatvia.security.turnstile.{$form->value}");
    }

    /**
     * The validation rules a protected form adds; none when it is not.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(BotCheckForm $form): array
    {
        return $this->protects($form) ? [self::FIELD => ['required', 'string', new Turnstile]] : [];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [self::FIELD.'.required' => __('Complete the security check.')];
    }

    /**
     * What the pages need to render the widget: the site key and the forms
     * it protects, or null when it is not set up.
     *
     * @return array{site_key: string, forms: list<string>}|null
     */
    public function forPages(): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        return [
            'site_key' => (string) config('services.turnstile.key'),
            'forms' => array_values(array_map(
                fn (BotCheckForm $form): string => $form->value,
                array_filter(BotCheckForm::cases(), $this->protects(...)),
            )),
        ];
    }
}
