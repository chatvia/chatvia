<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * The OAuth apps behind the "Sign in with …" buttons on the login and
 * register pages. Socialite reads them from `services.{provider}`. A blank
 * redirect uses this app's own callback URL.
 */
class SignInSettings extends AdminSettings
{
    #[ConfigKey('services.google.client_id')]
    public ?string $google_client_id = null;

    #[ConfigKey('services.google.client_secret'), ShouldBeEncrypted]
    public ?string $google_client_secret = null;

    #[ConfigKey('services.google.redirect', defaultPath: '/auth/google/callback')]
    public ?string $google_redirect = null;

    #[ConfigKey('services.microsoft.client_id')]
    public ?string $microsoft_client_id = null;

    #[ConfigKey('services.microsoft.client_secret'), ShouldBeEncrypted]
    public ?string $microsoft_client_secret = null;

    #[ConfigKey('services.microsoft.redirect', defaultPath: '/auth/microsoft/callback')]
    public ?string $microsoft_redirect = null;

    #[ConfigKey('services.github.client_id')]
    public ?string $github_client_id = null;

    #[ConfigKey('services.github.client_secret'), ShouldBeEncrypted]
    public ?string $github_client_secret = null;

    #[ConfigKey('services.github.redirect', defaultPath: '/auth/github/callback')]
    public ?string $github_redirect = null;

    #[ConfigKey('services.slack.client_id')]
    public ?string $slack_client_id = null;

    #[ConfigKey('services.slack.client_secret'), ShouldBeEncrypted]
    public ?string $slack_client_secret = null;

    #[ConfigKey('services.slack.redirect', defaultPath: '/auth/slack/callback')]
    public ?string $slack_redirect = null;

    public static function group(): string
    {
        return 'sign_in';
    }
}
