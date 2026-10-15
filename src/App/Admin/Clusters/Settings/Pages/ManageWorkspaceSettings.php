<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\WorkspaceSettings;
use BackedEnum;
use Closure;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * Open sign-up, the message limit of new workspaces, and how long
 * conversations are kept.
 */
class ManageWorkspaceSettings extends AdminSettingsPage
{
    #[\Override]
    protected static string $settings = WorkspaceSettings::class;

    #[\Override]
    protected static ?string $title = 'Workspaces';

    #[\Override]
    protected static ?string $slug = 'workspaces';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    #[\Override]
    protected static ?int $navigationSort = 10;

    public static function description(): string
    {
        return 'Sign-up, message limits and how long chats are kept.';
    }

    public static function overviewGroup(): string
    {
        return 'Access and security';
    }

    /**
     * Whether people can sign up.
     *
     * @return array{0: string, 1: string}
     */
    #[\Override]
    public function overviewStatus(): array
    {
        return $this->valueInUse('registration_enabled') ? ['Sign-up open', 'success'] : ['Sign-up closed', 'gray'];
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('sign_up', 'Sign-up and limits', 'Who can create a workspace, and how much a new one can use.', Heroicon::OutlinedUserPlus, fn (): array => [
                $this->toggle('registration_enabled', 'Open sign-up',
                    help: 'Anyone can create a workspace at /register. When off, the sign-up page is closed.'),
                $this->integer('new_workspace_message_limit', 'Monthly message limit of new workspaces',
                    help: 'How many agent replies a new workspace can get each month. 0 means no limit. To change the limit of one workspace, open it under Workspaces.', min: 0),
            ])->summary(fn (): string => sprintf(
                'Sign-up %s · %s',
                $this->valueInUse('registration_enabled') ? 'open' : 'closed',
                (int) $this->valueInUse('new_workspace_message_limit') === 0 ? 'no message limit' : $this->valueInUse('new_workspace_message_limit').' messages a month',
            )),
            SettingsCard::make('conversations', 'Conversations', 'How long conversations are kept.', Heroicon::OutlinedClock, fn (): array => [
                $this->integer('retention_days', 'Days to keep conversations',
                    help: 'Older conversations are deleted. A workspace can choose fewer days in its security settings.', min: 1),
                $this->integer('retention_floor_days', 'Fewest days a workspace may choose', min: 1)
                    // A floor above the days would leave a workspace no value to choose.
                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        if (is_numeric($value) && (int) $value > $this->retentionDays($get)) {
                            $fail('The fewest days cannot be more than the days to keep conversations.');
                        }
                    }),
            ])->summary(fn (): string => 'Kept '.$this->valueInUse('retention_days').' days'),
        ];
    }

    private function retentionDays(Get $get): int
    {
        return (int) (WorkspaceSettings::cast('retention_days', $get('retention_days')) ?? $this->defaultValue('retention_days'));
    }
}
