<?php

declare(strict_types=1);

namespace App\Admin\Pages;

use App\Admin\AdminPanelProvider;
use App\Admin\RequireSetup;
use App\Admin\Settings\Actions\CompleteSetupAction;
use App\Admin\Settings\Data\SetupData;
use App\Admin\Settings\PlatformSettings;
use Closure;
use Domain\Knowledge\Embedding\Data\EmbeddingColumnData;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Knowledge\Embedding\Queries\StoredEmbeddingColumnsQuery;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Providers\Contracts\ProviderAdapter;
use Domain\Llm\Services\ProviderAdapters;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * The setup of a new install, in two steps. Search first: hybrid search
 * (meaning plus BM25 keywords) and the embedding service and model, which
 * must return the vector size the database columns were created with. That
 * size is read from the database, and Next checks the service, model and key
 * with a test embedding. Then the AI provider and the model agents answer
 * with (its key is checked by loading the provider's model list; a provider
 * of the same service as search reuses its key). Every admin panel page
 * leads here until it is done or skipped ({@see RequireSetup}); it can be
 * run again later from Settings. Saved keys are never shown.
 *
 * @property-read Schema $form
 */
class Setup extends SimplePage
{
    /** What each search service returns and what else its key does, under the dropdown. */
    private const array EMBEDDING_DESCRIPTIONS = [
        'qwen' => 'Open-weight Qwen3-Embedding. Returns 32 to 4096 dimensions. The same key also ranks results and turns speech into text.',
        'openai' => 'text-embedding-3-small returns up to 1536 dimensions, text-embedding-3-large up to 3072. The same key can run GPT chat models.',
        'google' => 'gemini-embedding-001 returns 128 to 3072 dimensions. The same key can run Gemini chat models.',
        'cohere' => 'Returns 256, 512, 1024 or 1536 dimensions. The same key also ranks results.',
        'mistral' => 'Always returns 1024 dimensions. The same key also reads documents and PDFs.',
        'jina' => 'Returns 32 to 1024 dimensions.',
    ];

    private const array EMBEDDING_KEY_LABELS = [
        'qwen' => 'Scaleway AI API key',
        'openai' => 'OpenAI API key',
        'google' => 'Gemini API key',
        'cohere' => 'Cohere API key',
        'mistral' => 'Mistral API key',
        'jina' => 'Jina API key',
    ];

    /** The text embedded to check a search service. */
    private const string CHECK_TEXT = 'How do I reset my password?';

    #[\Override]
    protected Width|string|null $maxWidth = Width::FourExtraLarge;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, string> The models the chosen provider lists: id => label. */
    public array $models = [];

    /** Why the model list could not be loaded, or null. */
    public ?string $modelListError = null;

    /** Whether the provider refused the key (as opposed to having no list). */
    public bool $keyRefused = false;

    /** The provider, address and key the list was last loaded with, so Next does not load it again. */
    public ?string $loadedFor = null;

    /** The embedding columns as the database has them, read once per request. */
    private ?EmbeddingColumnData $storedColumn = null;

    public function mount(): void
    {
        // Once sources are trained, the search service stays as it is.
        $embedding = Chunk::query()->exists() ? null : $this->suggestedEmbedding();

        $this->form->fill([
            'hybrid_search' => (bool) (config('chatvia.vector.search.hybrid') ?? true),
            'embedding_provider' => $embedding?->value,
            'embedding_model' => $embedding instanceof EmbeddingProvider ? config($embedding->modelConfig()) : null,
            'chat_kind' => $this->suggestedChatKind($embedding),
        ]);
    }

    /** The page shows the large mark instead ({@see AdminPanelProvider}). */
    public function hasLogo(): bool
    {
        return false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Set up '.config('chatvia.brand.name');
    }

    public function getHeading(): string|Htmlable
    {
        return 'Welcome to '.config('chatvia.brand.name');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Two short steps and your agents can answer. You can change all of it later in Settings.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('complete'),
            Actions::make([$this->skipAction()])->alignCenter(),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                $this->searchStep(),
                $this->modelStep(),
            ])
                ->contained(false)
                ->extraAttributes(['class' => 'cv-setup-wizard'])
                ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                    <x-filament::button type="submit" icon="heroicon-m-check" wire:target="complete">
                        Finish setup
                    </x-filament::button>
                    BLADE))),
        ]);
    }

    public function complete(): void
    {
        $state = $this->form->getState();
        $this->checkChatModel();
        $kind = LlmProviderEnum::from((string) $state['chat_kind']);
        $modelId = (string) ($state['chat_model'] ?? $state['chat_model_id']);
        $embedding = isset($state['embedding_provider']) ? EmbeddingProvider::from($state['embedding_provider']) : null;

        $model = app(CompleteSetupAction::class)->execute(new SetupData(
            chatKind: $kind,
            chatBaseUrl: filled($state['chat_base_url'] ?? null) ? rtrim((string) $state['chat_base_url'], '/') : null,
            chatApiKey: $this->chatApiKey($state),
            chatModel: $this->listedModel($modelId) ?? new RemoteModelData($modelId, $modelId),
            hybridSearch: (bool) ($state['hybrid_search'] ?? true),
            embeddingProvider: $embedding,
            embeddingModel: filled($state['embedding_model'] ?? null) ? trim((string) $state['embedding_model']) : null,
            embeddingApiKey: filled($state['embedding_api_key'] ?? null) ? $state['embedding_api_key'] : null,
        ));

        Cache::forget($this->modelCacheKey());

        Notification::make()
            ->success()
            ->title(config('chatvia.brand.name').' is ready')
            ->body("New agents answer with {$model->descriptive_name}.")
            ->send();

        $this->redirect(Filament::getUrl());
    }

    /**
     * Load the chosen provider's model list with the address and key typed
     * so far. The list fills the model picker; an error is shown instead.
     */
    public function loadModels(): void
    {
        $this->models = [];
        $this->modelListError = null;
        $this->keyRefused = false;
        $this->loadedFor = $this->draftFingerprint();

        $provider = $this->draftProvider();

        if (! $provider instanceof LlmProvider) {
            return;
        }

        $adapter = $this->adapters()->for($provider->kind);

        try {
            $list = $adapter->models($provider);
        } catch (RequestException $e) {
            $this->keyRefused = in_array($e->response->status(), [401, 403], true);
            $this->modelListError = $this->keyRefused
                ? "{$adapter->label()} refused the API key. Check that you copied all of it."
                : "The model list could not be loaded ({$e->response->status()}). Type the model's name below.";

            return;
        } catch (ConnectionException) {
            $this->modelListError = "{$adapter->baseUrl($provider)} could not be reached. Check the address.";

            return;
        }

        Cache::put($this->modelCacheKey(), $list->keyBy('id')->map(fn (RemoteModelData $model): array => $model->toArray())->all(), now()->addMinutes(10));

        $this->models = $list->mapWithKeys(fn (RemoteModelData $model): array => [
            $model->id => $model->name === $model->id ? $model->id : "{$model->name} ({$model->id})",
        ])->all();

        if ($this->models === []) {
            $this->modelListError = "{$adapter->label()} lists no chat models. Type the model's name below.";
        }
    }

    // --- Steps ---------------------------------------------------------------

    private function searchStep(): Step
    {
        $trained = Chunk::query()->exists();

        return Step::make('Search')
            ->description('How agents find answers')
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->schema([
                Section::make('Hybrid search')
                    ->key('hybrid-search')
                    ->description('Search by meaning and by keywords at the same time.')
                    ->icon(Heroicon::OutlinedArrowsPointingIn)
                    ->afterLabel([$this->hybridSearchHelpAction()])
                    ->schema([
                        Toggle::make('hybrid_search')
                            ->label('Use hybrid search')
                            ->helperText('Recommended. You can change it at any time in Settings → Search.'),
                    ]),
                Section::make('Embedding model')
                    ->description('Turns your documents, and each question, into vectors.')
                    ->icon(Heroicon::OutlinedCubeTransparent)
                    ->schema($trained ? $this->trainedSearchFields() : $this->embeddingFields()),
            ])
            ->afterValidation(function () use ($trained): void {
                if (! $trained) {
                    $this->checkEmbedding();
                }

                // A chat provider of the same service reuses this key: its
                // models are ready when the next step opens.
                if ($this->reusesSearchKey($this->form->getRawState()) && $this->loadedFor !== $this->draftFingerprint()) {
                    $this->loadModels();
                }
            });
    }

    /** The "?" beside the hybrid search heading: what it does, in a popup. */
    private function hybridSearchHelpAction(): Action
    {
        return Action::make('hybridSearchHelp')
            ->label('What is hybrid search?')
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->iconButton()
            ->color('gray')
            ->modalHeading('What is hybrid search?')
            ->modalIcon(Heroicon::OutlinedArrowsPointingIn)
            ->modalWidth(Width::Large)
            ->modalContent(view('filament.setup-hybrid-search'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    /**
     * The search service, its model and key, under the vector size the
     * database stores: a service that cannot return it cannot be picked.
     *
     * @return list<Component>
     */
    private function embeddingFields(): array
    {
        $dimensions = $this->storedDimensions();
        $fitting = array_map(fn (EmbeddingProvider $provider): string => $provider->value, $this->fittingEmbeddings());

        return [
            Callout::make("Your database stores {$dimensions}-dimension vectors")
                ->description(new HtmlString(sprintf(
                    'Read from its embedding columns (<code>%s</code>). Use an embedding model that returns exactly %d dimensions. This size was set at install and cannot change.',
                    e($this->storedColumnType()),
                    $dimensions,
                )))
                ->icon(Heroicon::OutlinedCircleStack)
                ->info(),
            Select::make('embedding_provider')
                ->label('Service')
                ->options(collect(EmbeddingProvider::cases())->mapWithKeys(fn (EmbeddingProvider $provider): array => [
                    $provider->value => in_array($provider->value, $fitting, true)
                        ? $provider->label()
                        : "{$provider->label()} (cannot return {$dimensions} dimensions)",
                ])->all())
                ->disableOptionWhen(fn (string $value): bool => ! in_array($value, $fitting, true))
                ->in($fitting)
                ->native(false)
                ->selectablePlaceholder(false)
                ->helperText(fn (Get $get): ?string => self::EMBEDDING_DESCRIPTIONS[(string) $get('embedding_provider')] ?? null)
                ->required()
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                    $provider = EmbeddingProvider::tryFrom((string) $state);
                    $set('embedding_model', $provider instanceof EmbeddingProvider ? config($provider->modelConfig()) : null);

                    // Suggest the chat provider of the same service, until
                    // the chat step is filled in.
                    if (blank($get('chat_api_key')) && blank($get('chat_model')) && blank($get('chat_model_id'))) {
                        $set('chat_kind', $this->suggestedChatKind($provider));
                    }
                }),
            TextInput::make('embedding_model')
                ->label('Model')
                ->placeholder(fn (Get $get): ?string => ($provider = $this->embeddingProvider($get)) instanceof EmbeddingProvider ? (string) config($provider->modelConfig()) : null)
                ->helperText("The model's name exactly as the service writes it. It must return {$dimensions} dimensions: Next checks it with a test request.")
                ->required()
                ->maxLength(255)
                ->visible(fn (Get $get): bool => $this->embeddingProvider($get) instanceof EmbeddingProvider),
            TextInput::make('embedding_api_key')
                ->label(fn (Get $get): string => self::EMBEDDING_KEY_LABELS[$get('embedding_provider')] ?? 'API key')
                ->password()
                ->revealable()
                ->autocomplete('new-password')
                ->required()
                ->helperText('Stored encrypted. It is never shown again.')
                ->hintAction(fn (Get $get): ?Action => ($url = $this->embeddingProvider($get)?->keysUrl()) === null ? null : Action::make('getEmbeddingKey')
                    ->label('Get a key')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->iconPosition('after')
                    ->url($url, shouldOpenInNewTab: true))
                ->visible(fn (Get $get): bool => $this->embeddingProvider($get) instanceof EmbeddingProvider),
        ];
    }

    /**
     * Sources are trained with the search service in use, so it stays.
     *
     * @return list<Component>
     */
    private function trainedSearchFields(): array
    {
        $provider = EmbeddingProvider::tryFrom((string) config('chatvia.ai.models.embedding'));
        $model = $provider instanceof EmbeddingProvider ? (string) config($provider->modelConfig()) : null;

        return [
            Callout::make('Search is already set up')
                ->description(sprintf(
                    'Sources are trained with %s%s, %d dimensions (%s), so it stays as it is. It is in Settings → AI.',
                    $provider?->label() ?? 'the service in use',
                    filled($model) ? " ({$model})" : '',
                    $this->storedDimensions(),
                    $this->storedColumnType(),
                ))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->success(),
        ];
    }

    private function modelStep(): Step
    {
        return Step::make('AI model')
            ->description('What agents answer with')
            ->icon(Heroicon::OutlinedSparkles)
            ->schema([
                Section::make('Chat model')
                    ->description('New agents start with it. Each agent can change it later.')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->schema($this->chatFields()),
            ]);
    }

    /**
     * @return list<Component>
     */
    private function chatFields(): array
    {
        return [
            Select::make('chat_kind')
                ->label('Provider')
                ->options(collect($this->adapters()->all())->mapWithKeys(fn (ProviderAdapter $adapter): array => [$adapter->kind()->value => $adapter->label()])->all())
                ->native(false)
                ->selectablePlaceholder(false)
                ->helperText(fn (Get $get): ?string => $this->adapter($get('chat_kind'))?->description())
                ->in(array_map(fn (ProviderAdapter $adapter): string => $adapter->kind()->value, $this->adapters()->all()))
                ->required()
                ->live()
                ->afterStateUpdated(fn () => $this->loadModels()),
            TextInput::make('chat_base_url')
                ->label(fn (Get $get): string => $this->adapter($get('chat_kind'))?->defaultBaseUrl() === null ? 'Address' : 'Address (optional)')
                ->url()
                ->placeholder(fn (Get $get): string => $this->adapter($get('chat_kind'))?->defaultBaseUrl() ?? 'http://localhost:11434/v1')
                ->helperText(fn (Get $get): ?string => $this->adapter($get('chat_kind'))?->baseUrlHelp())
                ->required(fn (Get $get): bool => $this->adapter($get('chat_kind'))?->defaultBaseUrl() === null)
                ->visible(fn (Get $get): bool => $this->adapter($get('chat_kind'))?->acceptsBaseUrl() ?? false)
                ->live(onBlur: true)
                ->afterStateUpdated(fn () => $this->loadModels()),
            Callout::make(fn (Get $get): string => 'Uses your '.$this->adapter($get('chat_kind'))?->label().' key from the Search step.')
                ->icon(Heroicon::OutlinedKey)
                ->info()
                ->visible(fn (Get $get): bool => $this->reusesSearchKey($get)),
            TextInput::make('chat_api_key')
                ->label('API key')
                ->password()
                ->revealable()
                ->autocomplete('new-password')
                ->required(fn (Get $get): bool => $this->adapter($get('chat_kind'))?->needsApiKey() ?? true)
                ->helperText(fn (Get $get): string => ($this->adapter($get('chat_kind'))?->needsApiKey() ?? true)
                    ? 'Stored encrypted. It is never shown again.'
                    : 'Leave empty when your server has no key.')
                ->hintAction(fn (Get $get): ?Action => ($url = $this->adapter($get('chat_kind'))?->keysUrl()) === null ? null : Action::make('getKey')
                    ->label('Get a key')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->iconPosition('after')
                    ->url($url, shouldOpenInNewTab: true))
                ->visible(fn (Get $get): bool => ! $this->reusesSearchKey($get))
                ->live(onBlur: true)
                ->afterStateUpdated(fn () => $this->loadModels()),
            Callout::make(fn (): ?string => $this->modelListError)
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color(fn (): string => $this->keyRefused ? 'danger' : 'warning')
                ->visible(fn (): bool => filled($this->modelListError)),
            Select::make('chat_model')
                ->label('Model')
                ->options(fn (): array => $this->models)
                ->in(fn (): array => array_keys($this->models))
                ->searchable()
                ->required()
                ->placeholder('Pick a model')
                ->helperText(fn (): string => count($this->models).' chat models.')
                ->visible(fn (): bool => $this->models !== []),
            TextInput::make('chat_model_id')
                ->label('Model')
                ->placeholder('For example llama3.1:8b')
                ->helperText('The model\'s name exactly as your provider writes it.')
                ->required()
                ->maxLength(255)
                ->visible(fn (): bool => $this->models === [] && filled($this->modelListError) && ! $this->keyRefused),
            Text::make(fn (Get $get): string => ($this->adapter($get('chat_kind'))?->needsApiKey() ?? true)
                ? 'Type the API key to pick from the provider\'s models.'
                : 'Type the address (and key, if any) to pick from your server\'s models.')
                ->color('gray')
                ->visible(fn (): bool => $this->models === [] && blank($this->modelListError)),
        ];
    }

    public function skipAction(): Action
    {
        return Action::make('skip')
            ->label('Skip the setup for now')
            ->link()
            ->color('gray')
            ->visible(fn (): bool => blank(config('chatvia.admin.setup_completed_at')))
            ->requiresConfirmation()
            ->modalHeading('Skip the setup?')
            ->modalDescription('Agents cannot answer until an AI model is set up. You can run the setup later from Settings.')
            ->modalSubmitActionLabel('Skip')
            ->action(function (PlatformSettings $settings): void {
                $settings->setup_completed_at = now()->toIso8601String();
                $settings->save();

                $this->redirect(Filament::getUrl());
            });
    }

    // --- Checks ----------------------------------------------------------------

    /**
     * The last step's check, on Finish (a wizard runs no step hook there):
     * the model list loads with the key, unless it already did with what is
     * typed now, and a model must be picked.
     */
    private function checkChatModel(): void
    {
        if ($this->loadedFor !== $this->draftFingerprint()) {
            $this->loadModels();
        }

        $state = $this->form->getRawState();

        if ($this->keyRefused) {
            throw ValidationException::withMessages([
                $this->reusesSearchKey($state) ? 'data.chat_kind' : 'data.chat_api_key' => $this->modelListError,
            ]);
        }

        if (blank($state['chat_model'] ?? null) && blank($state['chat_model_id'] ?? null)) {
            throw ValidationException::withMessages([
                $this->models !== [] ? 'data.chat_model' : 'data.chat_model_id' => 'Pick the model your agents answer with.',
            ]);
        }
    }

    /**
     * Embed a short text with the service, model and key typed, as training
     * a source will. A refused key, an unknown model or a vector of another
     * size than the database stores stops the step.
     */
    private function checkEmbedding(): void
    {
        $state = $this->form->getRawState();
        $provider = EmbeddingProvider::from((string) $state['embedding_provider']);
        $dimensions = $this->storedDimensions();

        try {
            $returned = $this->withConfig([
                $provider->apiKeyConfig() => (string) $state['embedding_api_key'],
                $provider->modelConfig() => trim((string) $state['embedding_model']),
            ], fn (): int => count((array) app($provider->providerClass())->embed(self::CHECK_TEXT)));
        } catch (RequestException $e) {
            $status = $e->response->status();

            throw ValidationException::withMessages(in_array($status, [401, 403], true)
                ? ['data.embedding_api_key' => "{$provider->label()} refused the API key. Check that you copied all of it."]
                : ['data.embedding_model' => "{$provider->label()} could not use this model ({$status}). Check the model's name."]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['data.embedding_model' => "{$provider->label()} could not be reached. Try again."]);
        }

        if ($returned !== $dimensions) {
            throw ValidationException::withMessages(['data.embedding_model' => $returned === 0
                ? "{$provider->label()} returned no vector. Check the model's name."
                : "This model returned {$returned} dimensions, but the database stores {$dimensions}. Use a model that returns {$dimensions}."]);
        }
    }

    /**
     * Run a callback with some config values in place (the typed key and
     * model, which the embedding services read from config), then put the
     * old values back.
     *
     * @template T
     *
     * @param  array<string, string>  $values
     * @param  Closure(): T  $callback
     * @return T
     */
    private function withConfig(array $values, Closure $callback): mixed
    {
        $before = array_map(fn (string $key): mixed => config($key), array_combine(array_keys($values), array_keys($values)));

        config($values);

        try {
            return $callback();
        } finally {
            config($before);
        }
    }

    // --- Helpers -------------------------------------------------------------

    private function adapters(): ProviderAdapters
    {
        return app(ProviderAdapters::class);
    }

    /** The adapter of a kind picked in the form, or null for none the app can call. */
    private function adapter(mixed $kind): ?ProviderAdapter
    {
        $kind = LlmProviderEnum::tryFrom((string) $kind);

        return $kind instanceof LlmProviderEnum && $this->adapters()->has($kind) ? $this->adapters()->for($kind) : null;
    }

    private function embeddingProvider(Get $get): ?EmbeddingProvider
    {
        return EmbeddingProvider::tryFrom((string) $get('embedding_provider'));
    }

    /**
     * The chat provider's key: its own, or the search key when it is the
     * same service.
     *
     * @param  array<string, mixed>  $state
     */
    private function chatApiKey(array $state): ?string
    {
        $key = $this->reusesSearchKey($state) ? $state['embedding_api_key'] : ($state['chat_api_key'] ?? null);

        return filled($key) ? (string) $key : null;
    }

    /** The provider typed so far, unsaved, or null while something is missing. */
    private function draftProvider(): ?LlmProvider
    {
        $state = $this->form->getRawState();
        $adapter = $this->adapter($state['chat_kind'] ?? null);
        $key = (string) $this->chatApiKey($state);
        $url = $adapter?->acceptsBaseUrl() ? (string) ($state['chat_base_url'] ?? '') : '';

        if (! $adapter instanceof ProviderAdapter
            || ($adapter->needsApiKey() && $key === '')
            || ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false)
            || ($url === '' && $adapter->defaultBaseUrl() === null)) {
            return null;
        }

        return new LlmProvider([
            'kind' => $adapter->kind(),
            'name' => $adapter->label(),
            'base_url' => $url !== '' ? $url : null,
            'api_key' => $key !== '' ? $key : null,
        ]);
    }

    /** What the model list depends on: the provider, address and key typed. */
    private function draftFingerprint(): string
    {
        $state = $this->form->getRawState();

        return hash('sha256', implode("\n", [$state['chat_kind'] ?? '', $state['chat_base_url'] ?? '', (string) $this->chatApiKey($state)]));
    }

    /** What the provider's list said about a model, while it is cached. */
    private function listedModel(string $id): ?RemoteModelData
    {
        $listed = Cache::get($this->modelCacheKey())[$id] ?? null;

        return is_array($listed) ? RemoteModelData::from($listed) : null;
    }

    private function modelCacheKey(): string
    {
        return 'admin-setup:models:'.Filament::auth()->id();
    }

    /**
     * An embedding column as the database has it, or null before the first
     * migration. Every one has the same type: `app:check-embedding-storage`
     * stops a start otherwise.
     */
    private function storedColumn(): ?EmbeddingColumnData
    {
        return $this->storedColumn ??= app(StoredEmbeddingColumnsQuery::class)->get()->first();
    }

    /** The column type as PostgreSQL names it: `halfvec(1024)`. */
    private function storedColumnType(): string
    {
        return $this->storedColumn()->type ?? app(EmbeddingStorage::class)->columnType();
    }

    /** The vector size the database stores, which an embedding model must return. */
    private function storedDimensions(): int
    {
        return $this->storedColumn()?->dimensions() ?? app(EmbeddingStorage::class)->dimensions();
    }

    /**
     * The search services that return vectors of the size this install
     * stores (chosen when it was installed).
     *
     * @return list<EmbeddingProvider>
     */
    private function fittingEmbeddings(): array
    {
        return array_values(array_filter(
            EmbeddingProvider::cases(),
            fn (EmbeddingProvider $provider): bool => app($provider->providerClass())->fitsStorage(),
        ));
    }

    /** The search service in use when it fits, else the first that fits. */
    private function suggestedEmbedding(): ?EmbeddingProvider
    {
        $fitting = $this->fittingEmbeddings();
        $current = EmbeddingProvider::tryFrom((string) config('chatvia.ai.models.embedding'));

        return in_array($current, $fitting, true) ? $current : ($fitting[0] ?? null);
    }

    /**
     * The chat provider of the same service as search (Scaleway for Qwen3,
     * OpenAI, Google Gemini, Mistral), so one key does both; else the first
     * provider.
     */
    private function suggestedChatKind(?EmbeddingProvider $embedding): string
    {
        $adapters = $this->adapters()->all();
        $same = collect($adapters)->first(fn (ProviderAdapter $adapter): bool => $embedding instanceof EmbeddingProvider
            && ($adapter->settingsConfig()['api_key'] ?? null) === $embedding->apiKeyConfig());

        return ($same ?? $adapters[0])->kind()->value;
    }

    /**
     * Whether the chat provider reads its key from the same setting as the
     * search service typed in the first step, so it reuses that key.
     *
     * @param  Get|array<string, mixed>  $state  the form's, inside it or not
     */
    private function reusesSearchKey(Get|array $state): bool
    {
        $value = fn (string $key): mixed => $state instanceof Get ? $state($key) : ($state[$key] ?? null);
        $embedding = EmbeddingProvider::tryFrom((string) $value('embedding_provider'));
        $keyConfig = $this->adapter($value('chat_kind'))?->settingsConfig()['api_key'] ?? null;

        return $embedding instanceof EmbeddingProvider
            && filled($value('embedding_api_key'))
            && $keyConfig !== null
            && $embedding->apiKeyConfig() === $keyConfig;
    }
}
