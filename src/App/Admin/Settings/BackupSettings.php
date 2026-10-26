<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use App\Admin\Backup\Enums\BackupFrequency;

/**
 * The automatic backup of the install: whether it runs and how often
 * ({@see BackupFrequency}), how many backups are kept, the OpenPGP public
 * key backups are encrypted to, and what goes in besides the database and
 * Redis. The public key is not a secret: the server can only encrypt with
 * it, so a stolen server or bucket cannot read the backups.
 */
class BackupSettings extends AdminSettings
{
    #[ConfigKey('chatvia.backup.enabled')]
    public ?bool $enabled = null;

    /** @see BackupFrequency */
    #[ConfigKey('chatvia.backup.frequency')]
    public ?string $frequency = null;

    #[ConfigKey('chatvia.backup.keep')]
    public ?int $keep = null;

    #[ConfigKey('chatvia.backup.public_key')]
    public ?string $public_key = null;

    // Only with storage on this server: S3 storage already keeps the files
    // off it.
    #[ConfigKey('chatvia.backup.include_files')]
    public ?bool $include_files = null;

    // The Pulse and Spectra databases: server stats and the AI call log.
    #[ConfigKey('chatvia.backup.include_tool_databases')]
    public ?bool $include_tool_databases = null;

    public static function group(): string
    {
        return 'backup';
    }
}
