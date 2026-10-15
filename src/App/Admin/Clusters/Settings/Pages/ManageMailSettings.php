<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\MailField;
use App\Admin\Settings\MailProvider;
use App\Admin\Settings\MailSettings;
use App\Identity\Workspace\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * How the app sends email, in one section: a dropdown of every provider
 * Laravel ships with ({@see MailProvider}) that shows only the chosen one's
 * settings, then the sender. While nothing is set up, email is written to the log and nobody
 * receives it.
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
    protected static ?int $navigationSort = 12;

    public static function description(): string
    {
        return 'How Chatvia sends email: SMTP, Amazon SES, Mailgun, Postmark, Resend and more.';
    }

    public static function overviewGroup(): string
    {
        return 'Platform and brand';
    }

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Action::make('sendTestEmail')
                ->label('Send a test email')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
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

    public function overviewStatus(): array
    {
        $provider = $this->provider();

        if (! $provider->delivers()) {
            return ['Not sending email yet', 'warning'];
        }

        return [$provider->label(), $this->missing($provider) === [] ? 'success' : 'warning'];
    }

    protected function showsValueInUse(string $property): bool
    {
        return $property === 'mailer';
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('provider', 'Sending', 'The service that delivers email, what it needs, and the address emails come from. Horizon\'s queue alerts use a change after Horizon restarts.', Heroicon::OutlinedEnvelope, fn (): array => [
                Select::make('mailer')
                    ->label('Send email with')
                    ->options(fn (): array => $this->mailerOptions())
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->helperText(fn (Get $get): ?string => MailProvider::tryFrom((string) $get('mailer'))?->description())
                    ->in(fn (): array => array_keys($this->mailerOptions()))
                    ->required()
                    ->live(),
                ...array_merge(...array_map(
                    fn (MailProvider $provider): array => array_map(fn (MailField $field): Field => $this->providerField($provider, $field), $provider->fields()),
                    MailProvider::cases(),
                )),
                $this->email('from_address', 'Sender address'),
                $this->text('from_name', 'Sender name'),
            ])->summary(fn (): string => $this->providerSummary()),
        ];
    }

    /**
     * One setting of a provider, shown and required only while that provider
     * is picked. A saved secret is not asked for again.
     */
    private function providerField(MailProvider $provider, MailField $field): Field
    {
        $component = match ($field->kind) {
            'secret' => $this->secret($field->property, $field->label, $field->help, required: false),
            'integer' => $this->integer($field->property, $field->label, $field->help, min: 1, max: 65535),
            'choice' => $this->choice($field->property, $field->label, $field->options, $field->help),
            default => $this->text($field->property, $field->label, $field->help),
        };

        if ($field->placeholder !== null && $component instanceof TextInput) {
            $component->placeholder(fn (): string => is_scalar($default = $this->defaultValue($field->property)) && $default !== '' ? (string) $default : $field->placeholder);
        }

        if ($field->kind === 'secret' && ($url = $provider->keysUrl()) !== null && $component instanceof TextInput) {
            $component->hintAction(Action::make('getKey_'.$provider->value)
                ->label('Get a key')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->iconPosition('after')
                ->url($url, shouldOpenInNewTab: true));
        }

        return $component
            ->visible(fn (Get $get): bool => $get('mailer') === $provider->value)
            ->required(fn (Get $get): bool => $field->required
                && $get('mailer') === $provider->value
                && ($field->kind !== 'secret' || ! $this->isSaved($field->property)));
    }

    /**
     * Every provider, and a mailer set in .env that is not one of them
     * (`failover`, `ses-v2`, ...), so it can stay as it is.
     *
     * @return array<string, string>
     */
    private function mailerOptions(): array
    {
        $options = collect(MailProvider::cases())->mapWithKeys(fn (MailProvider $provider): array => [$provider->value => $provider->label()])->all();
        $inUse = (string) $this->valueInUse('mailer');

        return $inUse === '' || isset($options[$inUse]) ? $options : $options + [$inUse => "{$inUse} (set in .env)"];
    }

    /** The provider in use: the saved one, or the default (the log while nothing is set up). */
    private function provider(): MailProvider
    {
        return MailProvider::tryFrom((string) $this->valueInUse('mailer')) ?? MailProvider::Log;
    }

    /**
     * The labels of the required settings the provider still lacks.
     *
     * @return list<string>
     */
    private function missing(MailProvider $provider): array
    {
        return array_values(array_map(
            fn (MailField $field): string => $field->label,
            array_filter($provider->fields(), fn (MailField $field): bool => $field->required && blank($this->valueInUse($field->property))),
        ));
    }

    private function providerSummary(): string
    {
        $provider = $this->provider();
        $missing = $this->missing($provider);

        return match (true) {
            ! $provider->delivers() => 'Log only: nobody receives email',
            $missing !== [] => $provider->label().' · missing: '.implode(', ', $missing),
            default => $provider->label().' · from '.$this->shown($this->valueInUse('from_address')),
        };
    }

    private function admin(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }
}
