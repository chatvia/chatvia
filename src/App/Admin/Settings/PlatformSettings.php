<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * Install-wide options that fit nowhere else: the default language, the
 * chat widget, the tracking code, visitor location, where Horizon sends
 * alerts, how many queue workers it may run, where the programs that read
 * files are, whether the panel shows its Logs page, and when the admin setup
 * of the install was completed.
 */
class PlatformSettings extends AdminSettings
{
    /** @see SetupStatus */
    #[ConfigKey('chatvia.admin.setup_status')]
    public ?string $setup_status = null;

    #[ConfigKey('app.locale')]
    public ?string $default_locale = null;

    #[ConfigKey('chatvia.widget.extra_allowed_origins')]
    public ?string $widget_allowed_origins = null;

    #[ConfigKey('chatvia.widget.script_url_embed')]
    public ?string $widget_script_url = null;

    #[ConfigKey('chatvia.widget.stream_per_agent_per_minute')]
    public ?int $widget_stream_per_minute = null;

    #[ConfigKey('chatvia.widget.stream_per_agent_per_hour')]
    public ?int $widget_stream_per_hour = null;

    #[ConfigKey('chatvia.tracking_code')]
    public ?string $tracking_code = null;

    #[ConfigKey('services.ip2location.key'), ShouldBeEncrypted]
    public ?string $ip2location_api_key = null;

    #[ConfigKey('services.ip2location.endpoint')]
    public ?string $ip2location_endpoint = null;

    #[ConfigKey('horizon.notify_email')]
    public ?string $horizon_notify_email = null;

    // The most workers per Horizon supervisor (config/horizon.php). Horizon
    // reads them when it starts.
    #[ConfigKey('horizon.environments.production.chat.maxProcesses')]
    public ?int $chat_workers = null;

    #[ConfigKey('horizon.environments.production.ingestion.maxProcesses')]
    public ?int $ingestion_workers = null;

    #[ConfigKey('horizon.environments.production.analytics.maxProcesses')]
    public ?int $analytics_workers = null;

    #[ConfigKey('horizon.environments.production.media.maxProcesses')]
    public ?int $media_workers = null;

    // Each live voice call holds one of these workers for its whole length,
    // so this is also the most voice calls at once (VoiceCallSlots).
    #[ConfigKey('horizon.environments.production.voice.maxProcesses')]
    public ?int $voice_workers = null;

    // The programs the app runs (ServerProgram). Blank runs the program by
    // name, found on the PATH of the process that runs it. The panel takes
    // only the program's name or a full path that ends in it.
    #[ConfigKey('services.bin.pdfinfo')]
    public ?string $pdfinfo_path = null;

    #[ConfigKey('services.bin.pdftotext')]
    public ?string $pdftotext_path = null;

    #[ConfigKey('services.bin.pandoc')]
    public ?string $pandoc_path = null;

    #[ConfigKey('services.bin.catdoc')]
    public ?string $catdoc_path = null;

    #[ConfigKey('services.bin.catppt')]
    public ?string $catppt_path = null;

    #[ConfigKey('services.bin.ffmpeg')]
    public ?string $ffmpeg_path = null;

    #[ConfigKey('services.bin.ffprobe')]
    public ?string $ffprobe_path = null;

    #[ConfigKey('laravel-pdf.weasyprint.binary')]
    public ?string $weasyprint_path = null;

    // The Logs page reads and clears the install's log files, so it stays
    // out of the panel until an admin turns it on.
    #[ConfigKey('chatvia.admin.logs_page_enabled')]
    public ?bool $logs_page_enabled = null;

    public static function group(): string
    {
        return 'platform';
    }
}
