<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\MailSettings;
use App\Identity\Workspace\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * How the app sends email: through an SMTP server or Resend. While nothing is
 * set up, email is written to the log and nobody receives it.
 */
class ManageMailSettings extends AdminSettingsPage
{
    #[\Override]
    protected static string $settings = MailSettings::class;

    #[\Override]
    protected static ?string $title = 'Email';

    #[\Override]
    protected static ?string $slug = 'email';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    #[\Override]
    protected static ?int $navigationSort = 7;

    private const array MAILERS = [
        'smtp' => 'SMTP server',
        'resend' => 'Resend',
        'log' => 'Log only (no email is sent)',
    ];

    public static function description(): string
    {
        return 'How Chatvia sends email: SMTP or Resend.';
    }

    public static function overviewGroup(): string
    {
        return 'Connections';
    }

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Action::make('sendTestEmail')
                ->label('Send a test email')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->visible(fn (): bool => $this->canEdit())
                ->modalDescription(fn (): string => 'Sends a short email to '.($this->admin()?->email ?? 'you').' with the saved settings. Save your changes first.')
                ->modalSubmitActionLabel('Send')
                ->action(function (): void {
                    $admin = $this->admin();

                    if (! $admin instanceof User) {
                        return;
                    }

                    try {
                        Mail::raw(
                            'This is a test email from '.config('app.name').'. Your email settings work.',
                            fn ($message) => $message->to($admin->email)->subject('Test email from '.config('app.name')),
                        );

                        Notification::make()->success()->title('Test email sent')->body("Sent to {$admin->email}.")->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title('The test email failed')->body($e->getMessage())->persistent()->send();
                    }
                }),
        ];
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('sending', 'Sending', 'The service that delivers email, and the sender.', Heroicon::OutlinedEnvelope, fn (): array => [
                $this->choice('mailer', 'Send email with', self::MAILERS),
                $this->email('from_address', 'Sender address'),
                $this->text('from_name', 'Sender name'),
            ])->summary(fn (): string => sprintf(
                '%s · from %s',
                self::MAILERS[(string) $this->valueInUse('mailer')] ?? $this->shown($this->valueInUse('mailer')),
                $this->shown($this->valueInUse('from_address')),
            )),
            SettingsCard::make('smtp', 'SMTP server', 'Any email provider that offers SMTP.', Heroicon::OutlinedServerStack, fn (): array => [
                $this->text('smtp_host', 'Host', required: true),
                $this->integer('smtp_port', 'Port', help: 'Usually 587, or 465 for SMTPS.', min: 1, max: 65535),
                $this->choice('smtp_scheme', 'Encryption', [
                    'smtp' => 'STARTTLS (port 587)',
                    'smtps' => 'SMTPS (port 465)',
                ], help: 'Leave on the default to choose by the port.'),
                $this->text('smtp_username', 'Username'),
                $this->secret('smtp_password', 'Password', required: false),
            ])->required('smtp_host'),
            SettingsCard::make('resend', 'Resend', 'Sends email through the Resend API.', Heroicon::OutlinedPaperAirplane, fn (): array => [
                $this->secret('resend_api_key', 'API key'),
            ])->required('resend_api_key')->console('https://resend.com/api-keys'),
        ];
    }

    private function admin(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }
}
