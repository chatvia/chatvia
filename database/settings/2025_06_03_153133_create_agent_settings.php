<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('agent.message_history_enabled', true);
        $this->migrator->add('agent.message_history_retention', -1);

    }
};
