<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The Custom domains switch of App\Admin\Settings\PlatformSettings, empty
 * (off) until an admin turns it on. A migration of its own, so installs that
 * already ran the platform settings migration get the row too.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('platform.custom_domains_enabled');
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('platform.custom_domains_enabled');
    }
};
