<?php

declare(strict_types=1);

namespace App\Admin\Pages;

use App\Admin\AdminPanelProvider;
use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\RequireSetup;
use App\Admin\Settings\Actions\CompleteSetupAction;
use App\Admin\Settings\Data\SetupData;
use App\Admin\Settings\Data\SetupMailData;
use App\Admin\Settings\MailField;
use App\Admin\Settings\MailProvider;
use App\Admin\Settings\PlatformSettings;
use App\Admin\StaffToolsAccess;
use Closure;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Providers\Contracts\ProviderAdapter;
use Domain\Llm\Services\ProviderAdapters;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
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
 * The setup of a new install, in three steps: the AI provider and the model
 * agents answer with (its key is checked by loading the provider's model
 * list), search (the embedding service), and email (optional). Every admin
 * panel page leads here until it is done or skipped ({@see RequireSetup});
 * it can be run again later from Settings. Like the Settings pages it needs
 * two-factor authentication, and saved keys are never shown.
 *
 * @property-read Schema $form
 */
class Setup extends SimplePage
{
    /** What each search service needs and does, under the dropdown. */
    private const array EMBEDDING_DESCRIPTIONS = [
        'qwen' => 'Needs a Scaleway AI key. Also ranks results and turns speech into text.',
        'cohere' => 'Needs a Cohere key. Also ranks results.',
        'jina' => 'Needs a Jina key.',
        'mistral' => 'Needs a Mistral key. Also reads documents and PDFs.',
    ];

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

    public function mount(): void
    {
        $first = $this->adapters()->all()[0]->kind()->value;

        $this->form->fill([
            'chat_kind' => $first,
            'embedding_provider' => $this->suggestedEmbedding($first),
            'mailer' => 'later',
            'smtp_port' => 587,
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
        return 'Three short steps and your agents can answer. You can change all of it later in Settings.';
    }

    public function canEdit(): bool
    {
        return app(StaffToolsAccess::class)->allows();
    }

    public function content(Schema $schema): Schema
    {
        if (! $this->canEdit()) {
            return $schema->components([AdminSettingsPage::twoFactorRequired()]);
        }

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
                $this->modelStep(),
                $this->searchStep(),
                $this->emailStep(),
            ])->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                <x-filament::button type="submit" size="sm" icon="heroicon-m-check" wire:target="complete">
                    Finish setup
                </x-filament::button>
                BLADE))),
        ]);
    }

    public function complete(): void
    {
        abort_unless($this->canEdit(), 403);

        $state = $this->form->getState();
        $kind = LlmProviderEnum::from((string) $state['chat_kind']);
        $modelId = (string) ($state['chat_model'] ?? $state['chat_model_id']);

        $model = app(CompleteSetupAction::class)->execute(new SetupData(
            chatKind: $kind,
            chatBaseUrl: filled($state['chat_base_url'] ?? null) ? rtrim((string) $state['chat_base_url'], '/') : null,
            chatApiKey: filled($state['chat_api_key'] ?? null) ? $state['chat_api_key'] : null,
            chatModel: $this->listedModel($modelId) ?? new RemoteModelData($modelId, $modelId),
            embeddingProvider: isset($state['embedding_provider']) ? EmbeddingProvider::from($state['embedding_provider']) : null,
            embeddingApiKey: filled($state['embedding_api_key'] ?? null) ? $state['embedding_api_key'] : null,
            mail: $this->mailData($state),
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

    private function modelStep(): Step
    {
        return Step::make('AI model')
            ->description('What agents answer with')
            ->icon(Heroicon::OutlinedSparkles)
            ->schema([
                Select::make('chat_kind')
                    ->label('Provider')
                    ->options(collect($this->adapters()->all())->mapWithKeys(fn (ProviderAdapter $adapter): array => [$adapter->kind()->value => $adapter->label()])->all())
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->helperText(fn (Get $get): ?string => $this->adapter($get('chat_kind'))?->description())
                    ->in(array_map(fn (ProviderAdapter $adapter): string => $adapter->kind()->value, $this->adapters()->all()))
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        $set('embedding_provider', $this->suggestedEmbedding((string) $state));
                        $this->loadModels();
                    }),
                TextInput::make('chat_base_url')
                    ->label(fn (Get $get): string => $this->adapter($get('chat_kind'))?->defaultBaseUrl() === null ? 'Address' : 'Address (optional)')
                    ->url()
                    ->placeholder(fn (Get $get): string => $this->adapter($get('chat_kind'))?->defaultBaseUrl() ?? 'http://localhost:11434/v1')
                    ->helperText(fn (Get $get): ?string => $this->adapter($get('chat_kind'))?->baseUrlHelp())
                    ->required(fn (Get $get): bool => $this->adapter($get('chat_kind'))?->defaultBaseUrl() === null)
                    ->visible(fn (Get $get): bool => $this->adapter($get('chat_kind'))?->acceptsBaseUrl() ?? false)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn () => $this->loadModels()),
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
                    ->helperText(fn (): string => count($this->models).' chat models. New agents start with this one; each agent can change it.')
                    ->visible(fn (): bool => $this->models !== []),
                TextInput::make('chat_model_id')
                    ->label('Model')
                    ->placeholder('For example llama3.1:8b')
                    ->helperText('The model\'s name exactly as your provider writes it. New agents start with it.')
                    ->required()
                    ->maxLength(255)
                    ->visible(fn (): bool => $this->models === [] && filled($this->modelListError) && ! $this->keyRefused),
                Text::make(fn (Get $get): string => ($this->adapter($get('chat_kind'))?->needsApiKey() ?? true)
                    ? 'Type the API key to pick from the provider\'s models.'
                    : 'Type the address (and key, if any) to pick from your server\'s models.')
                    ->color('gray')
                    ->visible(fn (): bool => $this->models === [] && blank($this->modelListError)),
            ])
            ->afterValidation(function (): void {
                // The step's own check of the key: the list loads with it,
                // unless it already did with what is typed now.
                if ($this->loadedFor !== $this->draftFingerprint()) {
                    $this->loadModels();
                }

                if ($this->keyRefused) {
                    throw ValidationException::withMessages(['data.chat_api_key' => $this->modelListError]);
                }

                $state = $this->form->getRawState();

                if (blank($state['chat_model'] ?? null) && blank($state['chat_model_id'] ?? null)) {
                    throw ValidationException::withMessages([
                        $this->models !== [] ? 'data.chat_model' : 'data.chat_model_id' => 'Pick the model your agents answer with.',
                    ]);
                }
            });
    }

    private function searchStep(): Step
    {
        $trained = Chunk::query()->exists();
        $fitting = $this->fittingEmbeddings();

        return Step::make('Search')
            ->description('Searching your documents')
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->schema($trained ? [
                Callout::make('Search is already set up')
                    ->description('Sources are trained with it, so it stays as it is. It is in Settings → AI.')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->success(),
            ] : [
                Select::make('embedding_provider')
                    ->label('Search service')
                    ->options(array_combine(
                        array_map(fn (EmbeddingProvider $provider): string => $provider->value, $fitting),
                        array_map(fn (EmbeddingProvider $provider): string => $provider->label(), $fitting),
                    ))
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->helperText(fn (Get $get): string => trim((self::EMBEDDING_DESCRIPTIONS[$get('embedding_provider')] ?? '').' It turns your documents into vectors, and cannot change once your first source is trained.'))
                    ->in(array_map(fn (EmbeddingProvider $provider): string => $provider->value, $fitting))
                    ->required()
                    ->live(),
                TextInput::make('embedding_api_key')
                    ->label(fn (Get $get): string => match ($get('embedding_provider')) {
                        'qwen' => 'Scaleway AI API key',
                        'cohere' => 'Cohere API key',
                        'jina' => 'Jina API key',
                        'mistral' => 'Mistral API key',
                        default => 'API key',
                    })
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->required()
                    ->helperText('Stored encrypted. It is never shown again.')
                    ->visible(fn (Get $get): bool => filled($get('embedding_provider')) && ! $this->reusesChatKey($get)),
                Callout::make(fn (Get $get): string => 'Uses your '.$this->adapter($get('chat_kind'))?->label().' key from the first step.')
                    ->icon(Heroicon::OutlinedKey)
                    ->info()
                    ->visible(fn (Get $get): bool => $this->reusesChatKey($get)),
            ]);
    }

    private function emailStep(): Step
    {
        $providers = array_values(array_filter(MailProvider::cases(), fn (MailProvider $provider): bool => $provider->delivers()));
        $mailerIs = fn (string ...$mailers): Closure => fn (Get $get): bool => in_array($get('mailer'), $mailers, true);

        return Step::make('Email')
            ->description('Optional')
            ->icon(Heroicon::OutlinedEnvelope)
            ->schema([
                Select::make('mailer')
                    ->label('Send email with')
                    ->options(['later' => 'Not now'] + collect($providers)->mapWithKeys(fn (MailProvider $provider): array => [$provider->value => $provider->label()])->all())
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->helperText(fn (Get $get): ?string => $get('mailer') === 'later'
                        ? 'Emails are written to the log until you set this up in Settings → Email.'
                        : MailProvider::tryFrom((string) $get('mailer'))?->description())
                    ->in(['later', ...array_map(fn (MailProvider $provider): string => $provider->value, $providers)])
                    ->required()
                    ->live(),
                TextInput::make('from_address')
                    ->label('Sender address')
                    ->email()
                    ->required()
                    ->placeholder('chat@example.com')
                    ->visible(fn (Get $get): bool => ! in_array($get('mailer'), ['later', null], true)),
                ...array_merge(...array_map(
                    fn (MailProvider $provider): array => array_map(
                        fn (MailField $field): Field => $this->mailField($field, $provider)->visible($mailerIs($provider->value)),
                        $provider->fields(),
                    ),
                    $providers,
                )),
            ]);
    }

    /** One setting of an email provider ({@see MailProvider::fields()}). */
    private function mailField(MailField $field, MailProvider $provider): Field
    {
        if ($field->kind === 'choice') {
            return Select::make($field->property)
                ->label($field->label)
                ->options($field->options)
                ->in(array_keys($field->options))
                ->placeholder('Default')
                ->helperText($field->help);
        }

        $input = TextInput::make($field->property)
            ->label($field->label)
            ->helperText($field->help)
            ->placeholder($field->placeholder)
            ->required($field->required);

        if ($field->kind === 'integer') {
            $input->integer()->minValue(1)->maxValue(65535);
        }

        if ($field->kind === 'secret') {
            $input->password()->revealable()->autocomplete('new-password');

            if (($url = $provider->keysUrl()) !== null) {
                $input->hintAction(Action::make('getMailKey_'.$provider->value)
                    ->label('Get a key')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->iconPosition('after')
                    ->url($url, shouldOpenInNewTab: true));
            }
        }

        return $input;
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
                abort_unless($this->canEdit(), 403);

                $settings->setup_completed_at = now()->toIso8601String();
                $settings->save();

                $this->redirect(Filament::getUrl());
            });
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

    /** The provider typed so far, unsaved, or null while something is missing. */
    private function draftProvider(): ?LlmProvider
    {
        $state = $this->form->getRawState();
        $adapter = $this->adapter($state['chat_kind'] ?? null);
        $key = (string) ($state['chat_api_key'] ?? '');
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

        return hash('sha256', implode("\n", [$state['chat_kind'] ?? '', $state['chat_base_url'] ?? '', $state['chat_api_key'] ?? '']));
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

    /**
     * The search service that shares the chat provider's key (no second key),
     * else the first that fits.
     */
    private function suggestedEmbedding(string $chatKind): ?string
    {
        $fitting = $this->fittingEmbeddings();
        $keyConfig = $this->adapter($chatKind)?->settingsConfig()['api_key'] ?? null;
        $own = collect($fitting)->first(fn (EmbeddingProvider $provider): bool => $provider->apiKeyConfig() === $keyConfig);

        return ($own ?? $fitting[0] ?? null)?->value;
    }

    /** Whether the search service reads its key from the same setting as the chat provider's. */
    private function reusesChatKey(Get $get): bool
    {
        $embedding = EmbeddingProvider::tryFrom((string) $get('embedding_provider'));
        $keyConfig = $this->adapter($get('chat_kind'))?->settingsConfig()['api_key'] ?? null;

        return $embedding instanceof EmbeddingProvider && $keyConfig !== null && $embedding->apiKeyConfig() === $keyConfig;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function mailData(array $state): ?SetupMailData
    {
        $provider = MailProvider::tryFrom((string) ($state['mailer'] ?? ''));

        if (! $provider instanceof MailProvider || ! $provider->delivers()) {
            return null;
        }

        return new SetupMailData(
            provider: $provider,
            fromAddress: (string) $state['from_address'],
            values: collect($provider->fields())
                ->mapWithKeys(fn (MailField $field): array => [$field->property => $state[$field->property] ?? null])
                ->all(),
        );
    }
}
