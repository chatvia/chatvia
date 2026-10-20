<?php

declare(strict_types=1);

namespace App\Admin\Settings;

/**
 * What makes the app's PDFs (chat exports, the PDF of an office file for its
 * summary), by the value of `laravel-pdf.driver` (PlatformSettings::$pdf_maker).
 * WeasyPrint is a program on this server; the other two are Chromium
 * services, for a host that cannot install programs. Each value is a driver
 * of spatie/laravel-pdf.
 */
enum PdfMaker: string
{
    case Weasyprint = 'weasyprint';
    case Cloudflare = 'cloudflare';
    case Gotenberg = 'gotenberg';

    /** The maker in use; WeasyPrint for a driver the panel does not offer. */
    public static function inUse(): self
    {
        return self::tryFrom((string) config('laravel-pdf.driver')) ?? self::Weasyprint;
    }

    public function label(): string
    {
        return match ($this) {
            self::Weasyprint => 'WeasyPrint, a program on this server',
            self::Cloudflare => 'Cloudflare Browser Rendering',
            self::Gotenberg => 'Gotenberg, your own server',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Weasyprint => 'Needs the weasyprint program (Programs, below). The Docker image has it.',
            self::Cloudflare => 'Cloudflare makes the PDFs in its own browser. Needs a Cloudflare account and an API token. Nothing to install.',
            self::Gotenberg => 'A Gotenberg server you run, such as the gotenberg/gotenberg Docker image.',
        };
    }

    /** Where an admin creates the key, if the service has such a page. */
    public function keysUrl(): ?string
    {
        return $this === self::Cloudflare ? 'https://dash.cloudflare.com/profile/api-tokens' : null;
    }
}
