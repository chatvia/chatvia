<?php

declare(strict_types=1);

namespace App\Admin\Settings;

/**
 * Open sign-up, the monthly message limit a new workspace starts with,
 * whether an owner may download their data or delete their account from the
 * dashboard, whether new people get the dashboard tour, how long
 * conversations are kept (a workspace may choose fewer
 * days, never fewer than the minimum), and how long the dashboard keeps
 * notifications.
 */
class WorkspaceSettings extends AdminSettings
{
    #[ConfigKey('chatvia.registration_enabled')]
    public ?bool $registration_enabled = null;

    #[ConfigKey('chatvia.message_limits.new_workspace')]
    public ?int $new_workspace_message_limit = null;

    #[ConfigKey('chatvia.account.data_export')]
    public ?bool $owner_data_export = null;

    #[ConfigKey('chatvia.account.deletion')]
    public ?bool $owner_account_deletion = null;

    #[ConfigKey('chatvia.onboarding.tour')]
    public ?bool $onboarding_tour = null;

    #[ConfigKey('chatvia.retention.conversations_days')]
    public ?int $retention_days = null;

    #[ConfigKey('chatvia.retention.conversations_floor_days')]
    public ?int $retention_floor_days = null;

    #[ConfigKey('chatvia.notifications.retention_days')]
    public ?int $notification_retention_days = null;

    public static function group(): string
    {
        return 'workspaces';
    }
}
