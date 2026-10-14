<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\Actions\RemoveCustomLlmServerAction;
use App\Admin\Settings\AiProviderCheck;
use App\Admin\Settings\AiProviderSettings;
use App\Admin\Settings\AiProviderUsage;
use BackedEnum;
use Domain\Agent\Models\Agent;
use Domain\Llm\Actions\Catalog\SaveCustomLlmServerAction;
use Domain\Llm\Data\Catalog\CustomLlmServerData;
use Domain\Llm\Enums\AiCapability;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Enums\ModelListStatus;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\ModelListing;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The API key of each AI provider, entered once and used for everything the
 * provider does here: its chat models, embeddings, speech to text, documents
 * and reranking. Each card shows what the provider can do, with what it is
 * used for now marked. A key typed here is checked before it is saved.
 * Servers of your own (OpenAI-compatible) are listed below, each with its
 * own address and key.
 */
class ManageAiProviderSettings extends AdminSettingsPage implements HasTable
{
    use InteractsWithTable;

    /** Logos the app has; the other providers show an icon. */
    private const array LOGOS = [
        'azure' => 'images/llms/microsoft.svg',
        'openai' => 'images/llms/gpt.svg',
        'mistral' => 'images/llms/mistral.svg',
        'google' => 'images/llms/gemini.svg',
        'cohere' => 'images/llms/cohere.svg',
    ];

    #[\Override]
    protected static string $settings = AiProviderSettings::class;

    #[\Override]
    protected static ?string $title = 'AI providers';

    #[\Override]
    protected static ?string $slug = 'ai-providers';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    #[\Override]
    protected static ?int $navigationSort = 1;

    public static function description(): string
    {
        return 'The API keys of your AI services. Each key is entered once.';
    }

    public static function overviewGroup(): string
    {
        return 'AI and knowledge';
    }

    /**
     * The connected providers, or a warning while none is.
     *
     * @return array{0: string, 1: string}
     */
    #[\Override]
    public function overviewStatus(): array
    {
        $connected = array_map(
            fn (AiProvider $provider): string => $provider->label(),
            array_values(array_filter(AiProvider::cases(), fn (AiProvider $provider): bool => $provider->isConnected())),
        );
        $servers = $this->servers()->where('is_enabled', true)->count();

        if ($servers > 0) {
            $connected[] = $servers.' '.Str::plural('server', $servers).' of your own';
        }

        return $connected === []
            ? ['Not set up', 'warning']
            : [collect($connected)->join(', ', ' and ').' connected', 'success'];
    }

    /**
     * Check every new key before it is saved: a refused key stops the save;
     * a provider that cannot be reached saves with a warning.
     */
    #[\Override]
    public function save(): void
    {
        $state = $this->form->getState();
        $warnings = [];

        foreach (AiProvider::cases() as $provider) {
            $property = (string) AiProviderSettings::propertyFor($provider->apiKeyConfig());
            $typed = $state[$property] ?? null;

            if (blank($typed)) {
                continue;
            }

            $address = $provider->baseUrlConfig() !== null ? AiProviderSettings::propertyFor($provider->baseUrlConfig()) : null;
            $check = app(AiProviderCheck::class)->check($provider, (string) $typed, $address !== null ? ($state[$address] ?? null) : null);

            if ($check->keyRefused) {
                throw ValidationException::withMessages(["data.{$property}" => $check->message]);
            }

            if (! $check->connected && $check->message !== null) {
                $warnings[] = ['Saved, but the key could not be checked', $check->message];
            }

            if ($check->warning !== null) {
                $warnings[] = ["{$provider->label()} works, with a catch", $check->warning];
            }
        }

        parent::save();

        foreach ($warnings as [$title, $warning]) {
            Notification::make()->warning()->title($title)->body($warning)->persistent()->send();
        }
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkConnections')
                ->label('Check connections')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->action(function (): void {
                    $checked = 0;

                    foreach (array_filter(AiProvider::cases(), fn (AiProvider $provider): bool => $provider->isConnected()) as $provider) {
                        $check = app(AiProviderCheck::class)->check($provider);
                        $checked++;

                        match (true) {
                            ! $check->connected => Notification::make()->danger()->title("{$provider->label()} is not connected")->body($check->message)->send(),
                            $check->warning !== null => Notification::make()->warning()->title("{$provider->label()} is connected, with a catch")->body($check->warning)->persistent()->send(),
                            default => Notification::make()->success()->title("{$provider->label()} is connected")->send(),
                        };
                    }

                    if ($checked === 0) {
                        Notification::make()->warning()->title('No key is saved yet')->send();
                    }
                }),
        ];
    }

    protected function cards(): array
    {
        return [
            ...array_map($this->providerCard(...), AiProvider::cases()),
            SettingsCard::make('servers', 'Your own servers', 'Servers with an OpenAI-compatible API: vLLM, Ollama, LM Studio, or another service.', Heroicon::OutlinedServerStack, fn (): array => [
                EmbeddedTable::make(),
            ])->properties()->summary(function (): string {
                $count = $this->servers()->where('is_enabled', true)->count();

                return $count === 0 ? 'None yet' : $count.' '.Str::plural('server', $count);
            }),
        ];
    }

    #[\Override]
    protected function forgetDescription(string $property, string $label): string
    {
        $provider = array_find(AiProvider::cases(), fn (AiProvider $provider): bool => AiProviderSettings::propertyFor($provider->apiKeyConfig()) === $property);
        $kind = $provider?->chatKind();
        $agents = $kind instanceof LlmProviderEnum
            ? Agent::query()->whereHas('llm.provider', fn (Builder $query) => $query->where('kind', $kind))->count()
            : 0;
        $used = $provider instanceof AiProvider ? app(AiProviderUsage::class)->usedFor($provider) : [];

        return match (true) {
            $agents > 0 => "{$agents} ".Str::plural('agent', $agents)." answer with {$provider?->label()} models and stop answering until a key is added again.",
            $used !== [] => 'It is used for '.collect($used)->map(fn (AiCapability $capability): string => $capability->label())->join(', ', ' and ').'. That stops until a key is added again.',
            default => 'Nothing uses it right now.',
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->servers()->withCount(['llms' => fn (Builder $query) => $query->where('is_enabled', true)]))
            ->columns([
                TextColumn::make('name')
                    ->label('Server')
                    ->description(fn (LlmProvider $server): ?string => $server->base_url),
                TextColumn::make('key')
                    ->label('API key')
                    ->state(fn (LlmProvider $server): string => filled($server->api_key) ? 'Saved' : 'None')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Saved' ? 'success' : 'gray'),
                TextColumn::make('llms_count')
                    ->label('Models'),
                TextColumn::make('is_enabled')
                    ->label('Status')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'On' : 'Removed')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->headerActions([$this->addServerAction()])
            ->recordActions([$this->editServerAction(), $this->removeServerAction()])
            ->emptyStateHeading('No servers of your own')
            ->emptyStateDescription('Add one to use the models it serves.')
            ->emptyStateIcon(Heroicon::OutlinedServerStack)
            ->paginated(false);
    }

    // --- Cards -------------------------------------------------------------------

    private function providerCard(AiProvider $provider): SettingsCard
    {
        $key = (string) AiProviderSettings::propertyFor($provider->apiKeyConfig());
        $address = $provider->baseUrlConfig() !== null ? AiProviderSettings::propertyFor($provider->baseUrlConfig()) : null;

        $card = SettingsCard::make($provider->value, $provider->label(), $provider->description(), $this->iconOf($provider), fn (): array => [
            $this->secret($key, 'API key'),
            ...($provider->needsBaseUrl() && $address !== null ? [
                $this->url($address, 'Endpoint', help: 'Your resource\'s endpoint, from the Azure portal, such as https://my-resource.cognitiveservices.azure.com.', required: true),
            ] : []),
        ])
            ->required(...array_filter([$key, $provider->needsBaseUrl() ? $address : null]))
            ->console($provider->keysUrl(), 'Get a key')
            ->chips(fn (): array => $this->chips($provider))
            ->summary(fn (): string => 'Connected')
            ->helpFile('provider');

        if ($address !== null && ! $provider->needsBaseUrl()) {
            $card->advancedFields(fn (): array => [
                $this->url($address, 'Address', help: $provider === AiProvider::Scaleway
                    ? 'To bill a specific project, use https://api.scaleway.ai/{project-id}/v1.'
                    : 'Change it only to go through a proxy or another region.'),
            ]);
        }

        return $card;
    }

    /**
     * What the provider can do, each marked when it is used for it now.
     *
     * @return array<string, bool>
     */
    private function chips(AiProvider $provider): array
    {
        $used = app(AiProviderUsage::class)->usedFor($provider);

        return collect($provider->capabilities())
            ->mapWithKeys(fn (AiCapability $capability): array => [$capability->label() => in_array($capability, $used, true)])
            ->all();
    }

    private function iconOf(AiProvider $provider): string|Heroicon
    {
        if (isset(self::LOGOS[$provider->value])) {
            return asset(self::LOGOS[$provider->value]);
        }

        return match ($provider) {
            AiProvider::OpenRouter => Heroicon::OutlinedArrowsRightLeft,
            AiProvider::Groq => Heroicon::OutlinedBolt,
            AiProvider::Jina => Heroicon::OutlinedCubeTransparent,
            default => Heroicon::OutlinedCloud,
        };
    }

    // --- Servers of your own -----------------------------------------------------

    /**
     * @return Builder<LlmProvider>
     */
    private function servers(): Builder
    {
        return LlmProvider::query()->where('kind', LlmProviderEnum::CUSTOM)->orderBy('id');
    }

    private function addServerAction(): Action
    {
        return Action::make('addServer')
            ->label('Add a server')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add a server of your own')
            ->modalDescription('Next, add its models in Settings → AI models.')
            ->schema($this->serverFields(editing: false))
            ->action(function (array $data, Action $action): void {
                $this->saveServer($data, null, $action);

                Notification::make()->success()->title('Server added')->body('Add its models in Settings → AI models.')->send();
            });
    }

    private function editServerAction(): Action
    {
        return Action::make('editServer')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->modalHeading(fn (LlmProvider $record): string => "Edit {$record->name}")
            ->fillForm(fn (LlmProvider $record): array => [
                'name' => $record->name,
                'base_url' => $record->base_url,
                'headers' => $record->headers ?? [],
            ])
            ->schema($this->serverFields(editing: true))
            ->action(function (array $data, LlmProvider $record, Action $action): void {
                $this->saveServer($data, $record, $action);

                Notification::make()->success()->title('Server saved')->send();
            });
    }

    private function removeServerAction(): Action
    {
        return Action::make('removeServer')
            ->label('Remove')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (LlmProvider $record): bool => $record->is_enabled)
            ->requiresConfirmation()
            ->modalHeading(fn (LlmProvider $record): string => "Remove {$record->name}?")
            ->modalDescription(fn (LlmProvider $record): string => ($agents = $this->agentsUsing($record)) > 0
                ? "{$agents} ".Str::plural('agent', $agents).' answer with its models. Pick the model they use from now on.'
                : 'Its models are taken off the list agents can use.')
            ->schema(fn (LlmProvider $record): array => $this->agentsUsing($record) === 0 ? [] : [
                Select::make('replacement')
                    ->label('Model for these agents')
                    ->options(Llm::available()
                        ->reject(fn (Llm $llm): bool => $llm->llm_provider_id === $record->id)
                        ->mapWithKeys(fn (Llm $llm): array => [$llm->id => "{$llm->descriptive_name} ({$llm->provider->name})"])
                        ->all())
                    ->required()
                    ->native(false),
            ])
            ->modalSubmitActionLabel('Remove')
            ->action(function (array $data, LlmProvider $record): void {
                app(RemoveCustomLlmServerAction::class)->execute($record, isset($data['replacement']) ? Llm::query()->find($data['replacement']) : null);

                Notification::make()->success()->title("{$record->name} removed")->send();
            });
    }

    /**
     * @return list<Component>
     */
    private function serverFields(bool $editing): array
    {
        return [
            TextInput::make('name')
                ->label('Name')
                ->helperText('How the server is listed, such as "Ollama on the office server".')
                ->required()
                ->maxLength(100),
            TextInput::make('base_url')
                ->label('Address')
                ->url()
                ->placeholder('http://localhost:11434/v1')
                ->helperText('The address of its OpenAI-compatible API, usually ending in /v1.')
                ->required(),
            TextInput::make('api_key')
                ->label('API key')
                ->password()
                ->revealable()
                ->autocomplete('new-password')
                ->helperText($editing ? 'Leave empty to keep the saved key.' : 'Leave empty when your server has no key.'),
            Section::make('Advanced')
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->compact()
                ->secondary()
                ->collapsible()
                ->collapsed()
                ->schema([
                    KeyValue::make('headers')
                        ->label('Extra headers')
                        ->keyLabel('Header')
                        ->valueLabel('Value')
                        ->helperText('Sent with every request, for servers that need them.'),
                ]),
        ];
    }

    /**
     * Check the server with its model list, then save it. A refused key or
     * an address that cannot be reached keeps the window open.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveServer(array $data, ?LlmProvider $server, Action $action): void
    {
        $key = filled($data['api_key'] ?? null) ? (string) $data['api_key'] : $server?->api_key;
        $draft = new LlmProvider([
            'kind' => LlmProviderEnum::CUSTOM,
            'name' => (string) $data['name'],
            'base_url' => rtrim((string) $data['base_url'], '/'),
            'api_key' => $key,
            'headers' => array_filter((array) ($data['headers'] ?? []), filled(...)) ?: null,
        ]);
        $list = app(ModelListing::class)->list($draft);

        if (in_array($list->status, [ModelListStatus::KeyRefused, ModelListStatus::Unreachable], true)) {
            Notification::make()->danger()->title('The server was not saved')->body($list->message((string) $data['name'], (string) $draft->base_url))->send();
            $action->halt();
        }

        app(SaveCustomLlmServerAction::class)->execute(new CustomLlmServerData(
            name: (string) $data['name'],
            baseUrl: (string) $data['base_url'],
            apiKey: filled($data['api_key'] ?? null) ? (string) $data['api_key'] : null,
            headers: array_filter((array) ($data['headers'] ?? []), filled(...)),
        ), $server);
    }

    private function agentsUsing(LlmProvider $server): int
    {
        return Agent::query()->whereHas('llm', fn (Builder $query) => $query->where('llm_provider_id', $server->id))->count();
    }
}
