<?php

declare(strict_types=1);

namespace App\Admin\Resources\Workspaces\Pages;

use App\Admin\Pages\Dashboard;
use App\Admin\Pages\DashboardFilters;
use App\Admin\Resources\Workspaces\WorkspaceResource;
use App\Admin\Resources\Workspaces\WorkspaceStatus;
use App\Admin\Settings\ToolRecording;
use App\Admin\Widgets\MessagesChart;
use App\Admin\Widgets\WorkspaceStatsOverview;
use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Actions\Account\DeleteAccountAction;
use App\Identity\Workspace\Actions\Account\DisableTwoFactorAction;
use App\Identity\Workspace\Actions\Account\ReactivateAccountAction;
use App\Identity\Workspace\Actions\Account\UpdatePasswordAction;
use App\Identity\Workspace\Actions\Account\UpdateWorkspaceAction;
use App\Identity\Workspace\Actions\DataExport\RequestDataExportAction;
use App\Identity\Workspace\Actions\MessageLimits\SetMessageLimitAction;
use App\Identity\Workspace\Data\Account\UpdatePasswordData;
use App\Identity\Workspace\Data\Account\UpdateWorkspaceData;
use App\Identity\Workspace\Data\DataExport\RequestDataExportData;
use App\Identity\Workspace\Exceptions\ActiveDataExportExistsException;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use BackedEnum;
use Domain\Analytics\Enums\AnalyticsPeriod;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * One workspace's dashboard in the admin panel, in tabs: Overview (the
 * account, its people and its message limit; the page opens on it),
 * Analytics (this month's numbers and the last 30 days of messages),
 * Agents and Teammates. Staff act on the workspace from the header; every
 * action runs the same domain action the product uses.
 *
 * Editing renames the workspace and saves its owner's name and email; a
 * password reset is for the owner (an emailed link, or a new password set
 * here for an install that sends no email). Deactivating and deleting go
 * through the owner's account, which switches off every teammate and
 * chatter with it. Both are shown but disabled for a workspace with
 * platform staff in it: that would lock the admin out of this panel.
 */
class ViewWorkspace extends ViewRecord
{
    /** The Analytics tab's key in the `relation` query string. */
    private const string ANALYTICS_TAB = 'analytics';

    /** Why deactivate and delete are disabled for a workspace with platform staff in it. */
    private const string STAFF_WORKSPACE_REASON = 'Platform staff belong to this workspace. Switching it off would lock them out of this panel.';

    /** {@see self::hasPlatformStaff()}, worked out once per request. */
    private ?bool $hasPlatformStaff = null;

    #[\Override]
    protected static string $resource = WorkspaceResource::class;

    public function getTitle(): string
    {
        return $this->workspace()->name;
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): string
    {
        return 'Overview';
    }

    public function getContentTabIcon(): BackedEnum
    {
        return Heroicon::OutlinedSquares2x2;
    }

    /**
     * Overview first (the page opens on it), then Analytics, then the
     * relation tabs (Agents, Teammates).
     *
     * @return array<string|int, Tab|null>
     */
    public function getTabComponents(): array
    {
        $tabs = parent::getTabComponents();

        return ['' => $tabs[''] ?? null, self::ANALYTICS_TAB => $this->analyticsTab()] + $tabs;
    }

    /**
     * This month's numbers and the last 30 days of messages. Both load only
     * when the tab is first opened, so the page opens fast.
     */
    private function analyticsTab(): Tab
    {
        return Tab::make('Analytics')
            ->icon(Heroicon::OutlinedChartBar)
            ->schema([
                Livewire::make(WorkspaceStatsOverview::class, ['record' => $this->workspace()])
                    ->key('workspace-stats')
                    ->lazy(),
                Livewire::make(MessagesChart::class, [
                    'pageFilters' => [
                        DashboardFilters::PERIOD => AnalyticsPeriod::Last30Days->value,
                        DashboardFilters::WORKSPACE => $this->workspace()->id,
                    ],
                ])
                    ->key('workspace-messages-chart')
                    ->lazy(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->editAction(),
            $this->messageLimitAction(),
            $this->deactivateAction(),
            $this->reactivateAction(),
            ActionGroup::make([
                ActionGroup::make([
                    $this->openDashboardAction(),
                    $this->exportDataAction(),
                    $this->spectraAccessAction(),
                ])->dropdown(false),
                ActionGroup::make([
                    $this->sendPasswordResetLinkAction(),
                    $this->setPasswordAction(),
                    $this->disableTwoFactorAction(),
                ])->dropdown(false),
                $this->deleteAction(),
            ])
                ->label('More')
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->color('gray')
                ->button(),
        ];
    }

    /**
     * The workspace's name and its owner's name and email. A new email must
     * be confirmed again by the owner, as when they change it themselves.
     */
    private function editAction(): Action
    {
        return Action::make('edit')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->modalHeading('Edit workspace')
            ->modalSubmitActionLabel('Save')
            ->fillForm(fn (): array => [
                'name' => $this->workspace()->name,
                'owner_name' => $this->owner()?->name,
                'owner_email' => $this->owner()?->email,
            ])
            ->schema([
                TextInput::make('name')
                    ->label('Workspace name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('owner_name')
                    ->label('Owner name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('owner_email')
                    ->label('Owner email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->rule('lowercase')
                    ->unique(User::class, 'email', ignorable: fn (): ?User => $this->owner())
                    ->helperText('The owner signs in with it. A new email must be confirmed again before they can use the dashboard.'),
            ])
            ->visible(fn (): bool => $this->hasManageableOwner())
            ->action(function (array $data, UpdateWorkspaceAction $update): void {
                $update->execute(new UpdateWorkspaceData(
                    workspace: $this->workspace(),
                    name: (string) $data['name'],
                    ownerName: (string) $data['owner_name'],
                    ownerEmail: (string) $data['owner_email'],
                ));
                $this->refreshWorkspace();

                Notification::make()->success()->title('Workspace saved')->send();
            });
    }

    private function messageLimitAction(): Action
    {
        return Action::make('messageLimit')
            ->label('Set message limit')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('gray')
            ->fillForm(fn (Workspace $record): array => ['monthly_limit' => $record->messageLimit?->monthly_limit])
            ->schema([
                TextInput::make('monthly_limit')
                    ->label('Agent replies per month')
                    ->integer()
                    ->minValue(1)
                    ->helperText('Leave empty for no limit. The count of this month is kept.'),
            ])
            ->action(function (Workspace $record, array $data, SetMessageLimitAction $setLimit): void {
                $limit = filled($data['monthly_limit'] ?? null) ? (int) $data['monthly_limit'] : null;

                $setLimit->execute($record, $limit);
                $record->load('messageLimit');

                Notification::make()->success()->title($limit === null ? 'Message limit removed' : 'Message limit saved')->send();
            });
    }

    private function deactivateAction(): Action
    {
        return Action::make('deactivate')
            ->label('Deactivate')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->modalHeading('Deactivate this workspace?')
            ->modalDescription('The owner, every teammate and every chatter are signed out and cannot sign in again until you reactivate it. Its agents stop answering on every surface and channel, and voice calls end.')
            ->modalSubmitActionLabel('Deactivate')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Only staff see it.'),
            ])
            ->visible(fn (): bool => $this->owner() instanceof User
                && WorkspaceStatus::for($this->workspace()) === WorkspaceStatus::Active)
            ->disabled(fn (): bool => $this->hasPlatformStaff())
            ->tooltip(fn (): ?string => $this->hasPlatformStaff() ? self::STAFF_WORKSPACE_REASON : null)
            ->action(function (array $data, DeactivateAccountAction $deactivate): void {
                $deactivate->execute($this->ownerOrFail(), (string) $data['reason']);
                $this->refreshWorkspace();

                Notification::make()->success()->title('Workspace deactivated')->send();
            });
    }

    private function reactivateAction(): Action
    {
        return Action::make('reactivate')
            ->label('Reactivate')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Reactivate this workspace?')
            ->modalDescription('The owner, its teammates and its chatters can sign in again, and its agents answer again.')
            ->modalSubmitActionLabel('Reactivate')
            ->visible(fn (): bool => WorkspaceStatus::for($this->workspace()) === WorkspaceStatus::Deactivated)
            ->action(function (ReactivateAccountAction $reactivate): void {
                $reactivate->execute($this->ownerOrFail());
                $this->refreshWorkspace();

                Notification::make()->success()->title('Workspace reactivated')->send();
            });
    }

    private function openDashboardAction(): Action
    {
        return Action::make('openDashboard')
            ->label('Open in dashboard')
            ->icon(Heroicon::OutlinedChartBar)
            ->url(fn (): string => Dashboard::getUrl(['filters' => [DashboardFilters::WORKSPACE => $this->workspace()->id]]));
    }

    /**
     * The same email as "Forgot password" on the sign-in page. The current
     * password keeps working until the owner chooses a new one.
     */
    private function sendPasswordResetLinkAction(): Action
    {
        return Action::make('sendPasswordResetLink')
            ->label('Email a password reset link')
            ->icon(Heroicon::OutlinedEnvelope)
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedEnvelope)
            ->modalHeading('Email the owner a password reset link?')
            ->modalDescription(fn (): string => "{$this->ownerOrFail()->email} gets a link to choose a new password. The current password keeps working until they do.")
            ->modalSubmitActionLabel('Send link')
            ->visible(fn (): bool => $this->hasManageableOwner())
            ->action(function (): void {
                $email = $this->ownerOrFail()->email;
                $status = Password::sendResetLink(['email' => $email]);

                if ($status !== Password::RESET_LINK_SENT) {
                    Notification::make()->warning()->title('No link sent')->body(__($status))->send();

                    return;
                }

                Notification::make()->success()->title('Password reset link sent')->body("Sent to {$email}.")->send();
            });
    }

    /**
     * For an owner who cannot get the reset email, like `chatvia:admin:reset-password`
     * on the command line. The old password stops working at once.
     */
    private function setPasswordAction(): Action
    {
        return Action::make('setPassword')
            ->label('Set a new password')
            ->icon(Heroicon::OutlinedLockClosed)
            ->modalHeading('Set a new password for the owner?')
            ->modalDescription('Use this when the owner gets no email. The current password stops working at once. Give them the new one in a safe way. Two-step sign-in, if it is on, still applies.')
            ->modalSubmitActionLabel('Set password')
            ->schema([
                TextInput::make('password')
                    ->label('New password')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->required()
                    ->rule(PasswordRule::default()),
            ])
            ->visible(fn (): bool => $this->hasManageableOwner())
            ->action(function (array $data, UpdatePasswordAction $updatePassword): void {
                $updatePassword->execute(new UpdatePasswordData(
                    user: $this->ownerOrFail(),
                    password: (string) $data['password'],
                ));

                Notification::make()->success()->title('Password changed')->send();
            });
    }

    private function disableTwoFactorAction(): Action
    {
        return Action::make('disableTwoFactor')
            ->label('Turn off two-step sign-in')
            ->icon(Heroicon::OutlinedKey)
            ->requiresConfirmation()
            ->modalHeading('Turn off the owner\'s two-step sign-in?')
            ->modalDescription('Use this when the owner lost their authenticator app and their recovery codes. They sign in with their password only, until they turn it on again. Passkeys stay.')
            ->modalSubmitActionLabel('Turn off')
            ->visible(fn (): bool => $this->owner()?->usesTwoFactor() ?? false)
            ->action(function (DisableTwoFactorAction $disableTwoFactor): void {
                $disableTwoFactor->execute($this->ownerOrFail());
                $this->refreshWorkspace();

                Notification::make()->success()->title('Two-step sign-in turned off')->send();
            });
    }

    private function exportDataAction(): Action
    {
        return Action::make('exportData')
            ->label('Export data')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->requiresConfirmation()
            ->modalHeading('Export the workspace\'s data?')
            ->modalDescription('A ZIP of the workspace\'s data is prepared in the background. The owner gets an email with a download link when it is ready.')
            ->modalSubmitActionLabel('Export')
            ->visible(fn (): bool => $this->owner() instanceof User)
            ->action(function (RequestDataExportAction $requestExport): void {
                try {
                    $requestExport->execute(new RequestDataExportData($this->ownerOrFail()));
                } catch (ActiveDataExportExistsException) {
                    Notification::make()->warning()->title('An export is already being prepared or can be downloaded')->send();

                    return;
                }

                Notification::make()->success()->title('Export started')->body('The owner gets an email when it is ready.')->send();
            });
    }

    /**
     * Grant or take back the workspace's access to Spectra (the AI call log).
     * Spectra is install-wide, so the grant modal warns that the workspace's
     * staff will see every workspace's AI calls, not just their own.
     */
    private function spectraAccessAction(): Action
    {
        $granted = fn (): bool => $this->workspace()->spectra_access;

        return Action::make('spectraAccess')
            ->label(fn (): string => $granted() ? 'Take back Spectra access' : 'Allow Spectra access')
            ->icon(Heroicon::OutlinedCpuChip)
            ->requiresConfirmation()
            ->modalHeading(fn (): string => $granted() ? 'Take back Spectra access?' : 'Allow this workspace to open Spectra?')
            ->modalDescription(fn (): string => $granted()
                ? 'The workspace\'s owner and teammates can no longer open Spectra or see trace links in their chat logs. Nothing already recorded is removed.'
                : 'Everyone in this workspace can open Spectra and will see every AI call made on this install — every workspace\'s prompts, replies and costs, not just their own. Allow it only for a workspace you trust with that.')
            ->modalSubmitActionLabel(fn (): string => $granted() ? 'Take back' : 'Allow')
            ->color(fn (): string => $granted() ? 'gray' : 'warning')
            ->visible(fn (): bool => app(ToolRecording::class)->spectraInstalled())
            ->action(function () use ($granted): void {
                $allow = ! $granted();
                $this->workspace()->update(['spectra_access' => $allow]);

                Notification::make()->success()->title($allow ? 'Spectra access allowed' : 'Spectra access taken back')->send();
            });
    }

    private function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Delete workspace')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->modalIcon(Heroicon::OutlinedTrash)
            ->modalHeading('Delete this workspace?')
            ->modalDescription('Everyone is signed out at once. Then its agents, knowledge, chats, chatters, teammates and files are removed in the background. This cannot be undone. The owner gets no email.')
            ->modalSubmitActionLabel('Delete workspace')
            ->schema([
                TextInput::make('confirm_name')
                    ->label(fn (): string => "Type \"{$this->workspace()->name}\" to confirm")
                    ->required()
                    ->in(fn (): array => [$this->workspace()->name])
                    ->validationMessages(['in' => 'This is not the name of the workspace.']),
            ])
            ->visible(fn (): bool => $this->hasManageableOwner())
            ->disabled(fn (): bool => $this->hasPlatformStaff())
            ->tooltip(fn (): ?string => $this->hasPlatformStaff() ? self::STAFF_WORKSPACE_REASON : null)
            ->action(function (DeleteAccountAction $delete): void {
                $delete->execute($this->ownerOrFail());

                Notification::make()->success()->title("Deleting {$this->workspace()->name}")->body('Its data is being removed in the background.')->send();

                $this->redirect(WorkspaceResource::getUrl('index'));
            });
    }

    private function workspace(): Workspace
    {
        /** @var Workspace */
        return $this->getRecord();
    }

    private function owner(): ?User
    {
        return $this->workspace()->owner;
    }

    private function ownerOrFail(): User
    {
        return $this->owner() ?? throw new \LogicException("Workspace {$this->workspace()->id} has no owner.");
    }

    /** An owner whose account staff may still edit: not one being deleted. */
    private function hasManageableOwner(): bool
    {
        return $this->owner() instanceof User
            && WorkspaceStatus::for($this->workspace()) !== WorkspaceStatus::BeingDeleted;
    }

    private function hasPlatformStaff(): bool
    {
        return $this->hasPlatformStaff ??= $this->workspace()->hasPlatformAdmin();
    }

    /** Reload the owner, so the status, the badge and the actions show the change. */
    private function refreshWorkspace(): void
    {
        $this->workspace()->load(['owner', 'messageLimit']);
    }
}
