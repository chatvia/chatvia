<?php

declare(strict_types=1);

namespace App\Admin\Settings;

/**
 * The ways the app can send email, one per mailer Laravel ships with, by the
 * value of `mail.default` (MailSettings::$mailer). Each says what it is,
 * where to get its key, and the settings it needs ({@see MailField}); the
 * Email settings page and the admin setup show only the chosen one's.
 * Adding a provider means a case here, its `mail.mailers` entry and its
 * MailSettings properties.
 */
enum MailProvider: string
{
    case Smtp = 'smtp';
    case Ses = 'ses';
    case Mailgun = 'mailgun';
    case Postmark = 'postmark';
    case Resend = 'resend';
    case Cloudflare = 'cloudflare';
    case Sendmail = 'sendmail';
    case Log = 'log';

    public function label(): string
    {
        return match ($this) {
            self::Smtp => 'SMTP server',
            self::Ses => 'Amazon SES',
            self::Mailgun => 'Mailgun',
            self::Postmark => 'Postmark',
            self::Resend => 'Resend',
            self::Cloudflare => 'Cloudflare Email',
            self::Sendmail => 'Sendmail (this server)',
            self::Log => 'Log only (no email is sent)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Smtp => 'Any email provider that offers SMTP: your own server, Gmail, Microsoft 365, Brevo, SendGrid and others.',
            self::Ses => 'Amazon Simple Email Service, with an IAM access key that may send email.',
            self::Mailgun => 'Sends through the Mailgun API, from a domain verified there.',
            self::Postmark => 'Sends through the Postmark API with a server token.',
            self::Resend => 'Sends through the Resend API.',
            self::Cloudflare => 'Sends through Cloudflare\'s email API with an account ID and API token.',
            self::Sendmail => 'The sendmail program of the server Chatvia runs on.',
            self::Log => 'Emails are written to the log and nobody receives them. People cannot confirm a sign-up or accept an invitation.',
        };
    }

    /** Where an admin creates the key, if the provider has such a page. */
    public function keysUrl(): ?string
    {
        return match ($this) {
            self::Ses => 'https://console.aws.amazon.com/iam/home#/security_credentials',
            self::Mailgun => 'https://app.mailgun.com/settings/api_security',
            self::Postmark => 'https://account.postmarkapp.com/servers',
            self::Resend => 'https://resend.com/api-keys',
            self::Cloudflare => 'https://dash.cloudflare.com/profile/api-tokens',
            self::Smtp, self::Sendmail, self::Log => null,
        };
    }

    /**
     * The settings this provider needs, in the order they are asked for.
     *
     * @return list<MailField>
     */
    public function fields(): array
    {
        return match ($this) {
            self::Smtp => [
                MailField::text('smtp_host', 'Host', required: true, placeholder: 'smtp.example.com'),
                MailField::integer('smtp_port', 'Port', help: 'Usually 587, or 465 for SMTPS.'),
                MailField::choice('smtp_scheme', 'Encryption', [
                    'smtp' => 'STARTTLS (port 587)',
                    'smtps' => 'SMTPS (port 465)',
                ], help: 'Leave on the default to choose by the port.'),
                MailField::text('smtp_username', 'Username'),
                MailField::secret('smtp_password', 'Password', required: false),
            ],
            self::Ses => [
                MailField::text('ses_key', 'Access key ID', required: true),
                MailField::secret('ses_secret', 'Secret access key'),
                MailField::text('ses_region', 'Region', required: true, help: 'The region where the sending domain is verified, for example eu-west-1.'),
            ],
            self::Mailgun => [
                MailField::text('mailgun_domain', 'Domain', required: true, help: 'The sending domain verified in Mailgun.', placeholder: 'mg.example.com'),
                MailField::secret('mailgun_secret', 'API key'),
                MailField::choice('mailgun_endpoint', 'Region', [
                    'api.mailgun.net' => 'US',
                    'api.eu.mailgun.net' => 'EU',
                ], help: 'Where your Mailgun account is.'),
            ],
            self::Postmark => [
                MailField::secret('postmark_token', 'Server API token'),
            ],
            self::Resend => [
                MailField::secret('resend_api_key', 'API key'),
            ],
            self::Cloudflare => [
                MailField::text('cloudflare_account_id', 'Account ID', required: true),
                MailField::secret('cloudflare_token', 'API token', help: 'A token that may send email for the account.'),
            ],
            self::Sendmail, self::Log => [],
        };
    }

    /** Whether it delivers email to people (the log does not). */
    public function delivers(): bool
    {
        return $this !== self::Log;
    }
}
