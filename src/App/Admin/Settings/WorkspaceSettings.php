<?php

declare(strict_types=1);

namespace App\Admin\Settings;

/**
 * Open sign-up, the monthly message limit a new workspace starts with, and how
 * long conversations are kept (a workspace may choose fewer days, never fewer
 * than the minimum).
 */
class WorkspaceSettings extends AdminSettings
{
    #[ConfigKey('chatvia.registration_enabled')]
    public ?bool $registration_enabled = null;

    #[ConfigKey('chatvia.message_limits.new_workspace')]
    public ?int $new_workspace_message_limit = null;

    #[ConfigKey('chatvia.retention.conversations_days')]
    public ?int $retention_days = null;

    #[ConfigKey('chatvia.retention.conversations_floor_days')]
    public ?int $retention_floor_days = null;

    public static function group(): string
    {
        return 'workspaces';
    }
}
