<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * How the app sends email: the mailer (SMTP, Resend, or the log while
 * nothing is set up), the SMTP server and the sender address.
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

    #[ConfigKey('mail.from.address')]
    public ?string $from_address = null;

    #[ConfigKey('mail.from.name')]
    public ?string $from_name = null;

    public static function group(): string
    {
        return 'mail';
    }
}
