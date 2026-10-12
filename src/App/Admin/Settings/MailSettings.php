<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * How the app sends email: the provider ({@see MailProvider}, the log while
 * nothing is set up), that provider's server or keys, and the sender.
 */
class MailSettings extends AdminSettings
{
    #[ConfigKey('mail.default')]
    public ?string $mailer = null;

    #[ConfigKey('mail.mailers.smtp.host')]
    public ?string $smtp_host = null;

    #[ConfigKey('mail.mailers.smtp.port')]
    public ?int $smtp_port = null;

    #[ConfigKey('mail.mailers.smtp.scheme')]
    public ?string $smtp_scheme = null;

    #[ConfigKey('mail.mailers.smtp.username')]
    public ?string $smtp_username = null;

    #[ConfigKey('mail.mailers.smtp.password'), ShouldBeEncrypted]
    public ?string $smtp_password = null;

    #[ConfigKey('services.resend.key'), ShouldBeEncrypted]
    public ?string $resend_api_key = null;

    #[ConfigKey('services.ses.key')]
    public ?string $ses_key = null;

    #[ConfigKey('services.ses.secret'), ShouldBeEncrypted]
    public ?string $ses_secret = null;

    #[ConfigKey('services.ses.region')]
    public ?string $ses_region = null;

    #[ConfigKey('services.mailgun.domain')]
    public ?string $mailgun_domain = null;

    #[ConfigKey('services.mailgun.secret'), ShouldBeEncrypted]
    public ?string $mailgun_secret = null;

    #[ConfigKey('services.mailgun.endpoint')]
    public ?string $mailgun_endpoint = null;

    #[ConfigKey('services.postmark.token'), ShouldBeEncrypted]
    public ?string $postmark_token = null;

    #[ConfigKey('services.cloudflare.account_id')]
    public ?string $cloudflare_account_id = null;

    #[ConfigKey('services.cloudflare.token'), ShouldBeEncrypted]
    public ?string $cloudflare_token = null;

    #[ConfigKey('mail.from.address')]
    public ?string $from_address = null;

    #[ConfigKey('mail.from.name')]
    public ?string $from_name = null;

    public static function group(): string
    {
        return 'mail';
    }
}
