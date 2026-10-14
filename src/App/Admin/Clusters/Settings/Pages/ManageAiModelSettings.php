<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\Actions\RemoveLlmModelAction;
use App\Admin\Settings\AiSettings;
use BackedEnum;
use Closure;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Knowledge\Embedding\Exceptions\EmbeddingDimensionsMismatchException;
use Domain\Knowledge\Embedding\Rerank\RerankerFactory;
use Domain\Knowledge\Embedding\Rerank\RerankModelCatalog;
use Domain\Knowledge\Embedding\Services\EmbeddingCheck;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Embedding\Services\OpenRouterEmbeddingModels;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Llm\Actions\Catalog\AddLlmModelAction;
use Domain\Llm\Actions\Catalog\EnsureLlmProviderAction;
use Domain\Llm\Data\Catalog\ModelListData;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\AiCapability;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Enums\ModelListStatus;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\ModelListing;
use Domain\Llm\Services\ProviderAdapters;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * What runs on the connected AI providers: the chat models agents can use
 * (added from a provider's list, removed with their agents moved to another
 * model) and the default for new agents; the embedding service; the
 * reranker; document reading; speech to text. The models behind the app's
 * own jobs (Fast, Smart, Vision) and the PDF models are under Advanced.
 *
 * The embedding service and its model decide the stored vectors: once a
 * source is trained they are locked, and a service that cannot return the
 * stored vector size is refused. A service whose provider is not connected
 * cannot be picked; "Automatic" always can.
 */
class ManageAiModelSettings extends AdminSettingsPage implements HasTable
{
    use InteractsWithTable;

    private const string LOCKED_HELP = 'Locked: sources are already trained with it. Changing it needs every source trained again.';

    #[\Override]
    protected static string $settings = AiSettings::class;

    #[\Override]
    protected static ?string $title = 'AI models';

    #[\Override]
    protected static ?string $slug = 'ai-models';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    #[\Override]
    protected static ?int $navigationSort = 2;

    private ?bool $hasEmbeddings = null;

    public static function description(): string
    {
        return 'The models agents answer with, and the services for search, documents and speech.';
    }

    public static function overviewGroup(): string
    {
        return 'AI and knowledge';
    }

    /**
     * How many models agents can use, or a warning while there is none.
     *
     * @return array{0: string, 1: string}
     */
    #[\Override]
    public function overviewStatus(): array
    {
        $count = Llm::available()->count();

        return $count === 0
            ? ['No models yet', 'warning']
            : [$count.' '.Str::plural('model', $count).' for agents', 'success'];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('testEmbeddings')
                ->label('Test embeddings')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->action(function (): void {
                    $provider = EmbeddingProvider::configured();

                    if (! $provider instanceof EmbeddingProvider) {
                        Notification::make()->warning()->title('No embedding service is set')->send();

                        return;
                    }

                    $stored = app(EmbeddingStorage::class)->dimensions();
                    $result = app(EmbeddingCheck::class)->check($provider, dimensions: $stored);

                    $result->ok()
                        ? Notification::make()->success()->title("{$provider->label()} works")->body("It returned {$result->returned} dimensions, as the database stores.")->send()
                        : Notification::make()->danger()->title("{$provider->label()} does not work")->body($result->message($provider->label(), $stored))->send();
                }),
        ];
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('models', 'Models agents can use', 'Add models from your connected providers. Each agent picks one of them.', Heroicon::OutlinedChatBubbleLeftRight, fn (): array => [
                EmbeddedTable::make(),
                $this->modelChoice('default_agent_model', 'Default model for new agents',
                    help: 'The analytics assistant uses it too.',
                    unset: 'The first model on the list'),
            ])->summary(fn (): string => $this->modelsSummary()),
            SettingsCard::make('embeddings', 'Embeddings', 'Turn your documents, and each question, into vectors, so agents find the right part.', Heroicon::OutlinedCubeTransparent, fn (): array => [
                $this->embeddingChoice(),
                ...array_map($this->embeddingModel(...), EmbeddingProvider::cases()),
            ])->summary(fn (): string => $this->embeddingSummary()),
            SettingsCard::make('reranker', 'Reranker', 'Scores search results and tools against the question, so only relevant ones are used.', Heroicon::OutlinedBarsArrowDown, fn (): array => [
                $this->choice('reranker', 'Reranker', $this->withConnection([
                    'auto' => 'Automatic (OpenRouter when it is connected, else Cohere)',
                    'openrouter' => 'OpenRouter',
                    'cohere' => 'Cohere',
                    'custom' => 'Self-hosted',
                    'none' => 'Off (a basic similarity check)',
                ]), help: 'OpenRouter and Cohere use their keys from AI providers.')
                    ->live()
                    ->rule($this->connectedRule()),
                $this->choice('openrouter_rerank_model', 'OpenRouter model', app(RerankModelCatalog::class)->openRouterOptions(),
                    help: 'The strongest first. Each model has its own scores, so its own relevance bar.')
                    ->searchable()
                    ->visible(fn (Get $get): bool => $this->rerankerDriver($get) === 'openrouter'),
                $this->choice('cohere_rerank_model', 'Cohere model', app(RerankModelCatalog::class)->cohereOptions())
                    ->visible(fn (Get $get): bool => $this->rerankerDriver($get) === 'cohere'),
                $this->url('rerank_custom_url', 'Address',
                    help: 'A server with the common /rerank API (vLLM, Infinity, Jina-compatible), such as http://reranker:8000/v1.')
                    ->visible(fn (Get $get): bool => $get('reranker') === 'custom'),
                $this->secret('rerank_custom_key', 'API key', help: 'Only when your server asks for one.', required: false)
                    ->visible(fn (Get $get): bool => $get('reranker') === 'custom'),
                $this->text('rerank_custom_model', 'Model', help: 'The model name your server serves, such as Qwen/Qwen3-Reranker-4B.')
                    ->visible(fn (Get $get): bool => $get('reranker') === 'custom'),
            ])->summary(fn (): string => $this->rerankerSummary()),
            SettingsCard::make('documents', 'Documents', 'How PDFs, office files and images are read.', Heroicon::OutlinedDocumentMagnifyingGlass, fn (): array => [
                $this->choice('document_parser', 'Document reading', $this->withConnection([
                    'auto' => 'Automatic (Mistral, else Azure, when connected)',
                    'mistral' => 'Mistral OCR',
                    'azure' => 'Azure Document Intelligence',
                    'none' => 'Off',
                ]), help: 'Reads PDFs, Word, slides and images while sources are trained. Mistral also reads the pictures inside Word and PowerPoint files; Azure skips them. Excel, CSV, old office files and RTF are read by the app. Without a reader, PDF, Word, slides and images cannot be uploaded.')->rule($this->connectedRule()),
                $this->choice('document_llm', 'Whole-PDF reading', $this->withConnection([
                    'auto' => 'Automatic (Mistral when it is connected, else off)',
                    'mistral' => 'Mistral',
                    'none' => 'Off',
                ]), help: 'Document summaries and chat with PDF files. Off turns those two features off.')->rule($this->connectedRule()),
            ])->advancedFields(fn (): array => [
                $this->text('mistral_ocr_model', 'Mistral OCR model'),
            ])->summary(fn (): string => 'Reading: '.$this->resolvedLabel('document_parser', AiCapability::Documents, 'off')),
            SettingsCard::make('speech', 'Speech to text', 'Voice input, and audio and video sources.', Heroicon::OutlinedMicrophone, fn (): array => [
                $this->choice('transcriber', 'Speech to text', $this->withConnection([
                    'auto' => 'Automatic (Scaleway AI, else OpenAI)',
                    'scaleway' => 'Scaleway AI (Whisper)',
                    'openai' => 'OpenAI (Whisper)',
                    'none' => 'Off',
                ]), help: 'Off hides audio and video files from the sources.')->rule($this->connectedRule()),
            ])->advancedFields(fn (): array => [
                $this->text('scaleway_transcription_model', 'Scaleway AI model'),
                $this->text('openai_transcription_model', 'OpenAI model',
                    help: 'whisper-1, or gpt-4o-transcribe. Files up to 25 MB, about an hour and a half of audio.'),
            ])->summary(fn (): string => $this->resolvedLabel('transcriber', AiCapability::Speech, 'off')),
            SettingsCard::make('advanced', 'Models for background jobs', 'The models behind the app\'s own jobs, how much history agents see, and the PDF models.', Heroicon::OutlinedCog8Tooth, fn (): array => [
                $this->modelChoice('fast_model', 'Fast',
                    help: 'Short, frequent calls: conversation titles, topics, tone, guardrails, starters, translation and the eval judge. A small, cheap model.'),
                $this->modelChoice('smart_model', 'Smart',
                    help: 'Reading content: page summaries and tables while sources are trained.'),
                $this->modelChoice('vision_model', 'Vision',
                    help: 'Describes the images inside documents. Needs a model that reads images.'),
                $this->integer('history_messages', 'Messages of history', help: 'How many recent messages of a conversation the model sees on each turn.', min: 1, max: 50),
                $this->text('summary_model', 'Document summary model', help: 'A Mistral model, for whole-PDF reading.'),
                $this->text('analysis_model', 'Document analysis model', help: 'A Mistral model, for whole-PDF reading.'),
                $this->text('document_chat_model', 'PDF chat model',
                    help: 'Answers questions about PDF files in a chat. Needs PDF reading and function calling.'),
            ])->advanced(),
        ];
    }

    // --- Models agents can use ---------------------------------------------------

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Llm::query()
                ->with('provider')
                ->where('is_enabled', true)
                ->addSelect(['agents_count' => Agent::query()->selectRaw('count(*)')->whereColumn('agents.llm_id', 'llms.id')])
                ->orderBy('descriptive_name'))
            ->columns([
                TextColumn::make('descriptive_name')
                    ->label('Model')
                    ->description(fn (Llm $model): string => $model->internal_name)
                    ->suffix(fn (Llm $model): string => $model->external_name === $this->valueInUse('default_agent_model') ? ' · default' : ''),
                TextColumn::make('provider.name')
                    ->label('Provider')
                    ->description(fn (Llm $model): ?string => $model->provider->is_enabled && $model->provider->isConnected() ? null : 'Not connected')
                    ->color(fn (Llm $model): string => $model->provider->is_enabled && $model->provider->isConnected() ? 'gray' : 'warning'),
                TextColumn::make('can')
                    ->label('Can')
                    ->state(fn (Llm $model): array => array_values(array_filter([
                        ($model->features['function_calling'] ?? false) ? 'tools' : null,
                        ($model->features['vision'] ?? false) ? 'images' : null,
                    ])))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('context_window')
                    ->label('Context')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '' : Number::abbreviate($state).' tokens'),
                TextColumn::make('agents_count')
                    ->label('Agents'),
            ])
            ->headerActions([$this->addModelAction()])
            ->recordActions([$this->removeModelAction()])
            ->emptyStateHeading('No models yet')
            ->emptyStateDescription('Connect a provider in AI providers, then add its models here.')
            ->emptyStateIcon(Heroicon::OutlinedChatBubbleLeftRight)
            ->paginated(false);
    }

    private function addModelAction(): Action
    {
        return Action::make('addModel')
            ->label('Add a model')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add a model')
            ->modalDescription('Pick a connected provider, then one of its chat models.')
            ->schema([
                Select::make('source')
                    ->label('Provider')
                    ->options(fn (): array => $this->sources())
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(fn (Set $set): mixed => $set('model', null)),
                Callout::make(fn (Get $get): ?string => $this->listOf($get('source'))?->message($this->sourceLabel($get('source')), $this->sourceAddress($get('source'))))
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->warning()
                    ->visible(fn (Get $get): bool => filled($get('source')) && $this->listOf($get('source'))?->status !== ModelListStatus::Listed),
                Select::make('model')
                    ->label('Model')
                    ->options(fn (Get $get): array => collect($this->listOf($get('source'))->models ?? [])
                        ->mapWithKeys(fn (RemoteModelData $model): array => [$model->id => $model->name === $model->id ? $model->id : "{$model->name} ({$model->id})"])
                        ->all())
                    ->disableOptionWhen(fn (string $value, Get $get): bool => in_array($value, $this->addedModels($get('source')), true))
                    ->searchable()
                    ->required()
                    ->visible(fn (Get $get): bool => $this->listOf($get('source'))?->status === ModelListStatus::Listed),
                TextInput::make('model_id')
                    ->label('Model')
                    ->placeholder('For example llama3.1:8b')
                    ->helperText('The model\'s name exactly as your provider writes it.')
                    ->required()
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => ($list = $this->listOf($get('source'))) instanceof ModelListData && $list->keyAccepted() && $list->status !== ModelListStatus::Listed),
            ])
            ->action(function (array $data, Action $action): void {
                $provider = $this->providerOf((string) $data['source']);

                if (! $provider instanceof LlmProvider) {
                    $action->halt();

                    return;
                }

                $id = trim((string) ($data['model'] ?? $data['model_id'] ?? ''));
                $listed = collect($this->listOf((string) $data['source'])->models ?? [])->firstWhere('id', $id);
                $model = app(AddLlmModelAction::class)->execute($provider, $listed instanceof RemoteModelData ? $listed : new RemoteModelData($id, $id));

                Notification::make()->success()->title("{$model->descriptive_name} added")->body('Agents can pick it now.')->send();
            });
    }

    private function removeModelAction(): Action
    {
        return Action::make('removeModel')
            ->label('Remove')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->disabled(fn (Llm $record): bool => $this->agentsUsing($record) > 0 && $this->replacements($record) === [])
            ->tooltip(fn (Llm $record): ?string => $this->agentsUsing($record) > 0 && $this->replacements($record) === [] ? 'Agents use it: add another model first.' : null)
            ->requiresConfirmation()
            ->modalHeading(fn (Llm $record): string => "Remove {$record->descriptive_name}?")
            ->modalDescription(fn (Llm $record): string => ($agents = $this->agentsUsing($record)) > 0
                ? "{$agents} ".Str::plural('agent', $agents).' answer with it. Pick the model they use from now on.'
                : 'Agents can no longer pick it. You can add it again later.')
            ->schema(fn (Llm $record): array => $this->agentsUsing($record) === 0 ? [] : [
                Select::make('replacement')
                    ->label('Model for these agents')
                    ->options($this->replacements($record))
                    ->default(fn (): ?int => Llm::available()->firstWhere('external_name', $this->valueInUse('default_agent_model'))?->getKey())
                    ->required()
                    ->native(false),
            ])
            ->modalSubmitActionLabel('Remove')
            ->action(function (array $data, Llm $record): void {
                app(RemoveLlmModelAction::class)->execute($record, isset($data['replacement']) ? Llm::query()->find($data['replacement']) : null);
                $this->fillCards();

                Notification::make()->success()->title("{$record->descriptive_name} removed")->send();
            });
    }

    /**
     * The providers a model can be added from: the connected accounts with
     * chat models, then the servers of your own.
     *
     * @return array<string, string>
     */
    private function sources(): array
    {
        $accounts = collect(AiProvider::cases())
            ->filter(fn (AiProvider $provider): bool => $provider->can(AiCapability::Chat) && $provider->isConnected())
            ->mapWithKeys(fn (AiProvider $provider): array => ["account:{$provider->value}" => $provider->label()]);
        $servers = LlmProvider::query()->where('kind', LlmProviderEnum::CUSTOM)->where('is_enabled', true)->orderBy('id')->get()
            ->mapWithKeys(fn (LlmProvider $server): array => ["server:{$server->id}" => $server->name]);

        return $accounts->merge($servers)->all();
    }

    /** The provider row a source names: the account's (made when missing) or a server of your own. */
    private function providerOf(string $source): ?LlmProvider
    {
        [$type, $value] = array_pad(explode(':', $source, 2), 2, '');

        return match ($type) {
            'account' => ($kind = AiProvider::tryFrom($value)?->chatKind()) instanceof LlmProviderEnum && AiProvider::from($value)->isConnected()
                ? app(EnsureLlmProviderAction::class)->execute($kind)
                : null,
            'server' => LlmProvider::query()->where('kind', LlmProviderEnum::CUSTOM)->where('is_enabled', true)->find((int) $value),
            default => null,
        };
    }

    /** An unsaved row to read a source's list with, without making the account's row yet. */
    private function draftOf(mixed $source): ?LlmProvider
    {
        [$type, $value] = array_pad(explode(':', (string) $source, 2), 2, '');

        return match ($type) {
            'account' => ($kind = AiProvider::tryFrom($value)?->chatKind()) instanceof LlmProviderEnum
                ? new LlmProvider(['kind' => $kind, 'name' => AiProvider::from($value)->label()])
                : null,
            'server' => LlmProvider::query()->where('kind', LlmProviderEnum::CUSTOM)->find((int) $value),
            default => null,
        };
    }

    /** The source's model list, read once every ten minutes. */
    private function listOf(mixed $source): ?ModelListData
    {
        $draft = $this->draftOf($source);

        if (! $draft instanceof LlmProvider) {
            return null;
        }

        $adapters = app(ProviderAdapters::class);
        $key = 'admin-ai-models:list:'.hash('sha256', implode("\n", [(string) $source, $adapters->for($draft->kind)->baseUrl($draft), (string) $adapters->for($draft->kind)->apiKey($draft)]));

        return once(fn (): ModelListData => ModelListData::from(Cache::remember($key, now()->addMinutes(10), fn (): array => app(ModelListing::class)->list($draft)->toArray())));
    }

    private function sourceLabel(mixed $source): string
    {
        return $this->sources()[(string) $source] ?? 'The provider';
    }

    private function sourceAddress(mixed $source): string
    {
        $draft = $this->draftOf($source);

        return $draft instanceof LlmProvider ? app(ProviderAdapters::class)->for($draft->kind)->baseUrl($draft) : '';
    }

    /**
     * The ids of the source's models agents can use already.
     *
     * @return list<string>
     */
    private function addedModels(mixed $source): array
    {
        $draft = $this->draftOf($source);

        if (! $draft instanceof LlmProvider) {
            return [];
        }

        return Llm::query()
            ->where('is_enabled', true)
            ->whereHas('provider', fn (Builder $query) => $draft->exists ? $query->whereKey($draft->id) : $query->where('kind', $draft->kind))
            ->pluck('internal_name')
            ->all();
    }

    private function agentsUsing(Llm $model): int
    {
        return (int) ($model->getAttribute('agents_count') ?? Agent::query()->where('llm_id', $model->id)->count());
    }

    /**
     * The models agents can move to: the others they can use.
     *
     * @return array<int, string>
     */
    private function replacements(Llm $model): array
    {
        return Llm::available()
            ->reject(fn (Llm $other): bool => $other->is($model))
            ->mapWithKeys(fn (Llm $other): array => [$other->id => "{$other->descriptive_name} ({$other->provider->name})"])
            ->all();
    }

    private function modelsSummary(): string
    {
        $count = Llm::available()->count();

        if ($count === 0) {
            return 'No models yet';
        }

        $default = $this->catalogName($this->valueInUse('default_agent_model')) ?? Llm::available()->first()?->descriptive_name;

        return $count.' '.Str::plural('model', $count).' · default: '.$default;
    }

    /**
     * A catalog model, by its alias, for one of the app's jobs. Unset uses
     * the default model.
     */
    private function modelChoice(string $property, string $label, string $help, string $unset = 'Same as the default model'): Select
    {
        return Select::make($property)
            ->label($label)
            ->helperText($help)
            ->options(fn (): array => $this->catalogOptions())
            ->in(fn (): array => array_merge(...array_map(array_keys(...), array_values($this->catalogOptions()))))
            ->searchable()
            ->placeholder($unset);
    }

    /**
     * The models that may be picked, by provider: alias => name.
     *
     * @return array<string, array<string, string>>
     */
    private function catalogOptions(): array
    {
        return Llm::available()
            ->groupBy(fn (Llm $llm): string => $llm->provider->name)
            ->map(fn ($models): array => $models->mapWithKeys(fn (Llm $llm): array => [$llm->external_name => $llm->descriptive_name])->all())
            ->all();
    }

    private function catalogName(mixed $alias): ?string
    {
        return Llm::catalog()->firstWhere('external_name', (string) $alias)?->descriptive_name;
    }

    // --- Services ----------------------------------------------------------------

    /**
     * Options for a service choice, each one whose provider is not
     * connected marked so.
     *
     * @param  array<string, string>  $options
     * @return array<string, string>
     */
    private function withConnection(array $options): array
    {
        return collect($options)
            ->map(fn (string $label, string $value): string => ($provider = AiProvider::tryFrom($value)) instanceof AiProvider && ! $provider->isConnected()
                ? "{$label} (not connected)"
                : $label)
            ->all();
    }

    /** Refuses a provider whose key is not set: it would not work. */
    private function connectedRule(): Closure
    {
        return fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            $provider = AiProvider::tryFrom((string) $value);

            if ($provider instanceof AiProvider && ! $provider->isConnected()) {
                $fail("Connect {$provider->label()} in AI providers first.");
            }
        };
    }

    /** The reranker driver chosen, with "Automatic" resolved. */
    private function rerankerDriver(Get $get): string
    {
        $driver = (string) ($get('reranker') ?: $this->defaultValue('reranker'));

        return $driver === 'auto' ? app(RerankerFactory::class)->autoDriver() : $driver;
    }

    /** The reranker in use, for the card's summary line. */
    private function rerankerSummary(): string
    {
        $reranker = app(Reranker::class);

        return $reranker->isEnabled() ? $reranker->model() : 'Off: a basic similarity check';
    }

    /** The provider a driver resolves to now, by name. */
    private function resolvedLabel(string $property, AiCapability $capability, string $none): string
    {
        $driver = strtolower((string) $this->valueInUse($property));
        $provider = $driver === 'auto' ? AiProvider::firstConnectedFor($capability) : AiProvider::tryFrom($driver);

        return $provider?->label() ?? $none;
    }

    private function embeddingSummary(): string
    {
        $provider = EmbeddingProvider::tryFrom((string) $this->valueInUse('embedding_provider'));

        return $provider instanceof EmbeddingProvider
            ? $provider->label().' ('.config($provider->modelConfig()).')'
            : $this->shown($this->valueInUse('embedding_provider'));
    }

    private function embeddingChoice(): Field
    {
        $providers = collect(EmbeddingProvider::cases())
            ->mapWithKeys(fn (EmbeddingProvider $provider): array => [$provider->value => $provider->label().($provider->account()->isConnected() ? '' : ' (not connected)')])
            ->all();

        return $this->choice('embedding_provider', 'Service', $providers)
            ->disabled(fn (): bool => $this->hasEmbeddings())
            ->live()
            ->helperText(fn (): string => $this->hasEmbeddings()
                ? self::LOCKED_HELP
                : 'It must return the vector size this install stores. Its provider must be connected.')
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                $provider = EmbeddingProvider::tryFrom((string) $value);

                if (! $provider instanceof EmbeddingProvider) {
                    return;
                }

                if (! $provider->account()->isConnected()) {
                    $fail("Connect {$provider->account()->label()} in AI providers first.");

                    return;
                }

                $error = $this->dimensionsError($provider);

                if ($error !== null) {
                    $fail($error);
                }
            });
    }

    /**
     * The model field of one embedding service, shown while it is the one
     * chosen: OpenRouter's from its list, the others' typed.
     */
    private function embeddingModel(EmbeddingProvider $provider): Field
    {
        $property = (string) AiSettings::propertyFor($provider->modelConfig());
        $listed = $provider === EmbeddingProvider::OpenRouter;
        $field = $listed
            ? $this->choice($property, 'Model', app(OpenRouterEmbeddingModels::class)->options(app(EmbeddingStorage::class)->dimensions()))->searchable()
            : $this->text($property, 'Model');

        return $field
            ->disabled(fn (): bool => $this->hasEmbeddings())
            ->helperText(fn (): string => match (true) {
                $this->hasEmbeddings() => self::LOCKED_HELP,
                $listed => 'OpenRouter\'s embedding models. "Test embeddings" checks that the saved one returns the size this install stores.',
                default => 'The model\'s name exactly as the service writes it.',
            })
            ->visible(fn (Get $get): bool => ($get('embedding_provider') ?: $this->defaultValue('embedding_provider')) === $provider->value);
    }

    private function hasEmbeddings(): bool
    {
        return $this->hasEmbeddings ??= Chunk::query()->exists();
    }

    /**
     * Why the provider cannot be used with the stored vectors, or null: the
     * same check the app makes when it builds the provider.
     */
    private function dimensionsError(EmbeddingProvider $provider): ?string
    {
        $embeddings = app($provider->providerClass());
        $stored = app(EmbeddingStorage::class)->dimensions();

        return $embeddings->getDimensions() === $stored
            ? null
            : EmbeddingDimensionsMismatchException::for($embeddings, $stored)->getMessage();
    }
}
