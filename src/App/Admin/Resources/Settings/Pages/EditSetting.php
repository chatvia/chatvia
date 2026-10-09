<?php

namespace App\Admin\Resources\Settings\Pages;

use App\Admin\Resources\Settings\SettingResource;
use App\Admin\Settings\AdminSetting;
use App\Admin\Settings\SettingField;
use App\Admin\Settings\SettingGroup;
use App\Admin\Settings\SettingsStore;
use App\Identity\Workspace\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Edit one settings group. Secrets are write-only: the form never receives
 * their saved value, a blank input keeps it, and the trash button next to the
 * input removes it.
 *
 * @property-read AdminSetting $record
 */
class EditSetting extends EditRecord
{
    #[\Override]
    protected static string $resource = SettingResource::class;

    /**
     * The row is created on first visit, so the URL works for every group
     * before anything is saved.
     */
    protected function resolveRecord(int|string $key): Model
    {
        abort_unless(SettingResource::canManage(), 403);

        $group = SettingGroup::tryFrom((string) $key) ?? abort(404);

        return AdminSetting::forGroup($group);
    }

    public function getTitle(): string
    {
        return $this->group()->title();
    }

    public function getSubheading(): string
    {
        return $this->group()->description();
    }

    protected function getHeaderActions(): array
    {
        $consoleUrl = $this->group()->consoleUrl();

        return [
            Action::make('console')
                ->label('Open provider console')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url($consoleUrl, shouldOpenInNewTab: true)
                ->visible($consoleUrl !== null),
            Action::make('removeSavedValues')
                ->label('Remove saved values')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Every value of this group goes back to the value in the .env file.')
                ->modalSubmitActionLabel('Remove')
                ->visible(fn (): bool => $this->record->hasSavedValues())
                ->action(function (): void {
                    $this->record->update(['payload' => null, 'updated_by' => $this->admin()?->id]);
                    $this->fillForm();

                    Notification::make()->success()->title('Saved values removed')->send();
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $group = $this->group();
        $store = app(SettingsStore::class);
        $callbackUrls = $group->callbackUrls();

        return $schema
            ->columns(1)
            ->components([
                Section::make('Credentials')
                    ->description('Encrypted before they are saved. Secrets are never shown again: leave one blank to keep it.')
                    ->icon(Heroicon::OutlinedKey)
                    ->afterHeader([
                        Text::make(fn (): string => $store->status($group, $this->record)->getLabel())
                            ->badge()
                            ->color(fn (): string => $store->status($group, $this->record)->getColor())
                            ->icon(fn (): Heroicon => $store->status($group, $this->record)->getIcon()),
                    ])
                    ->columns(2)
                    ->schema(array_map(fn (SettingField $field): TextInput => $this->input($field, $store), $group->fields())),
                Section::make('Setup')
                    ->description('Register these URLs with the provider.')
                    ->icon(Heroicon::OutlinedLink)
                    ->visible($callbackUrls !== [])
                    ->schema(array_map(
                        fn (string $label, string $url): TextEntry => TextEntry::make('callback_'.Str::snake($label))
                            ->label($label)
                            ->state($url)
                            ->fontFamily(FontFamily::Mono)
                            ->copyable()
                            ->copyMessage('Copied'),
                        array_keys($callbackUrls),
                        $callbackUrls,
                    )),
            ]);
    }

    private function input(SettingField $field, SettingsStore $store): TextInput
    {
        $envValue = $store->envValue($field);
        $hasEnvValue = filled($envValue);

        $input = TextInput::make("payload.{$field->name}")
            ->label($field->label)
            ->helperText($field->help)
            ->password($field->secret)
            ->autocomplete($field->secret ? 'new-password' : 'off')
            ->rules(['nullable', 'string', 'max:2000', ...$field->rules])
            ->placeholder(fn (): ?string => match (true) {
                $field->secret && filled($this->record->value($field)) => '•••••••• saved, leave blank to keep',
                $field->secret && $hasEnvValue => '•••••••• set in .env',
                ! $field->secret && $hasEnvValue => (string) $envValue,
                default => null,
            })
            ->hint(fn (): ?string => match (true) {
                filled($this->record->value($field)) => 'Saved',
                $hasEnvValue => 'From .env',
                $field->required => 'Required',
                default => null,
            })
            ->hintIcon(fn (): ?Heroicon => match (true) {
                filled($this->record->value($field)) => Heroicon::OutlinedLockClosed,
                $hasEnvValue => Heroicon::OutlinedDocumentText,
                default => null,
            })
            ->hintColor(fn (): string => match (true) {
                filled($this->record->value($field)) => 'success',
                $hasEnvValue => 'info',
                default => 'gray',
            });

        return $field->secret ? $input->suffixAction($this->forgetAction($field, $hasEnvValue)) : $input;
    }

    private function forgetAction(SettingField $field, bool $hasEnvValue): Action
    {
        return Action::make('forget')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->tooltip('Remove the saved value')
            ->visible(fn (): bool => filled($this->record->value($field)))
            ->requiresConfirmation()
            ->modalHeading("Remove the saved {$field->label}?")
            ->modalDescription($hasEnvValue
                ? 'The value in the .env file is used again.'
                : 'There is no value in the .env file, so this stops working until you add one.')
            ->modalSubmitActionLabel('Remove')
            ->action(function () use ($field): void {
                $this->record->forgetValue($field, $this->admin());

                Notification::make()->success()->title("{$field->label} removed")->send();
            });
    }

    /**
     * Never send a saved secret to the browser.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $payload = [];

        foreach ($this->group()->fields() as $field) {
            $payload[$field->name] = $field->secret ? null : $this->record->value($field);
        }

        return ['payload' => $payload];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return [
            'payload' => $this->record->payloadFrom($data['payload'] ?? []),
            'updated_by' => $this->admin()?->id,
        ];
    }

    /**
     * Clear the secret inputs that were just saved.
     */
    protected function afterSave(): void
    {
        $this->fillForm();
    }

    protected function getSavedNotificationTitle(): string
    {
        return 'Settings saved';
    }

    private function group(): SettingGroup
    {
        return $this->record->key;
    }

    private function admin(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }
}
