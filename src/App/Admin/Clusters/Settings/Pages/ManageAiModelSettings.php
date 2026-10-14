<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\Actions\ReindexEmbeddingsAction;
use App\Admin\Settings\Actions\RemoveLlmModelAction;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\Data\ConnectionCheckData;
use App\Admin\Settings\DocumentServiceCheck;
use BackedEnum;
use Closure;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Knowledge\Embedding\Exceptions\EmbeddingDimensionsMismatchException;
use Domain\Knowledge\Embedding\Rerank\RerankerFactory;
use Domain\Knowledge\Embedding\Rerank\RerankModelCatalog;
use Domain\Knowledge\Embedding\Services\EmbeddingCheck;
use Domain\Knowledge\Embedding\Services\EmbeddingReindexStatus;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Embedding\Services\OpenRouterEmbeddingModels;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Services\DocumentReaders;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswerQuestion;
use Domain\Llm\Actions\Catalog\AddLlmModelAction;
use Domain\Llm\Actions\Catalog\EnsureLlmProviderAction;
use Domain\Llm\Data\Catalog\ModelListData;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\AiCapability;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Enums\ModelListStatus;
use Domain\Llm\Enums\RealtimeVoice;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\DocumentLlm;
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
use Illuminate\Bus\Batch;
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
 * The embedding service and its model decide the stored vectors, and a
 * service that cannot return the stored vector size is refused. Once
 * documents are stored as vectors, a change warns that they must be embedded
 * again and asks before it saves; saving starts that re-index in the queue
 * ({@see ReindexEmbeddingsAction}), and the card shows how far it got. "Re-index
 * documents" starts one by hand. A service whose provider is not connected
 * cannot be picked; "Automatic" always can. The Documents services have no
 * "Automatic": they show the one in use, and a new pick is checked with a
 * real call as it is saved ({@see DocumentServiceCheck}).
 */
class ManageAiModelSettings extends AdminSettingsPage implements HasTable
{
    use InteractsWithTable;

    private const string CHANGE_WARNING = 'Your documents were turned into vectors with the current model. Another model makes different vectors, so agents cannot find your documents until every document, Q&A and tool is embedded again. Saving starts this re-index in the background queue. With many documents it can take hours, and until it ends agents may miss documents.';

    private const string REINDEX_NOTE = 'Files are not read again: only the embeddings are made again.';

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

    private ?bool $hasStoredVectors = null;

    /**
     * The Documents checks made in this request: a save reads the form twice.
     *
     * @var array<string, ConnectionCheckData>
     */
    private array $documentChecks = [];

    /** The last re-index, read once per request (false: not read yet). */
    private Batch|false|null $latestReindex = false;

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
            Action::make('reindex')
                ->label('Re-index documents')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->disabled(fn (): bool => $this->runningReindex() instanceof Batch)
                ->tooltip(fn (): ?string => $this->runningReindex() instanceof Batch ? 'A re-index is running.' : null)
                ->requiresConfirmation()
                ->modalIcon(Heroicon::OutlinedArrowPath)
                ->modalIconColor('warning')
                ->modalHeading('Re-index all documents?')
                ->modalDescription('Every document, Q&A and tool is embedded again with the current embedding model. It runs in the background queue and, with many documents, can take hours. Until it ends, agents may miss some documents. '.self::REINDEX_NOTE)
                ->modalSubmitActionLabel('Re-index')
                ->action(function (): void {
                    $this->latestReindex = app(ReindexEmbeddingsAction::class)->execute();

                    Notification::make()->success()->title('Re-index started')->body('It runs in the background queue. Its progress shows under Embeddings.')->send();
                }),
        ];
    }

    /**
     * Saves, but first asks when the change makes the stored vectors useless
     * (another embedding service or model): saving then starts a re-index.
     */
    #[\Override]
    public function save(): void
    {
        if ($this->changesStoredVectors($this->form->getState())) {
            $this->mountAction('changeEmbeddings');

            return;
        }

        parent::save();
    }

    public function changeEmbeddingsAction(): Action
    {
        return Action::make('changeEmbeddings')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('warning')
            ->modalHeading('Change the embedding model?')
            ->modalDescription(self::CHANGE_WARNING.' '.self::REINDEX_NOTE)
            ->modalSubmitActionLabel('Save and re-index')
            ->color('warning')
            ->action(function (): void {
                $this->saveSettings();

                Notification::make()->success()->title('Settings saved. Re-index started')->body('It runs in the background queue. Its progress shows under Embeddings.')->send();
            });
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
                $this->reindexCallout(),
                $this->embeddingChoice(),
                ...array_map($this->embeddingModel(...), EmbeddingProvider::cases()),
                Callout::make('Changing the embedding model means a re-index')
                    ->description(self::CHANGE_WARNING)
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->warning()
                    ->visible(fn (): bool => $this->changesStoredVectors($this->data ?? [])),
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
                $this->documentChoice('document_parser', 'Document reading', [
                    'mistral' => 'Mistral OCR',
                    'azure' => 'Azure Document Intelligence',
                ], 'Reads the text, scans and tables of PDFs, Word, slides and images while sources are trained. Mistral OCR also reads the pictures inside Word and PowerPoint files; Azure skips them. Excel, CSV, old office files and RTF are read by the app. Off: PDF, Word, slides and images cannot be uploaded.',
                    fn (AiProvider $provider): ConnectionCheckData => app(DocumentServiceCheck::class)->reader($provider)),
                $this->documentChoice('document_llm', 'Summaries and PDF chat', [
                    'mistral' => 'Mistral',
                ], 'Mistral\'s models read each document whole: they write a short summary of every source, and answer questions about PDF files attached in a chat. Off turns both off.',
                    fn (AiProvider $provider): ConnectionCheckData => app(DocumentServiceCheck::class)->wholePdf($provider)),
            ])->advancedFields(fn (): array => [
                $this->text('mistral_ocr_model', 'Mistral OCR model'),
            ])->summary(fn (): string => $this->documentsSummary()),
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
            SettingsCard::make('voice', 'Voice conversations', 'Live spoken calls with an agent, in the hosted chat and the chat widget.', Heroicon::OutlinedSpeakerWave, fn (): array => [
                $this->choice('voice', 'Voice conversations', $this->withConnection([
                    'auto' => 'Automatic (OpenAI when it is connected, else off)',
                    'openai' => 'OpenAI Realtime',
                    'none' => 'Off',
                ]), help: 'Each agent still turns them on (Setup → Settings → Composer options). A call costs much more than a typed chat.')->rule($this->connectedRule()),
                $this->choice('openai_realtime_voice', 'Default voice', collect(RealtimeVoice::cases())->mapWithKeys(fn (RealtimeVoice $voice): array => [$voice->value => $voice->label()])->all(),
                    help: 'Agents speak with it unless they pick their own. Marin and Cedar sound the most natural.'),
                $this->integer('voice_max_call_minutes', 'Longest call, in minutes', help: 'The agent says goodbye and hangs up then.', min: 1, max: 25),
            ])->advancedFields(fn (): array => [
                $this->choice('openai_realtime_model', 'Model', [
                    'gpt-realtime-2.1' => 'GPT Realtime 2.1',
                    'gpt-realtime-2.1-mini' => 'GPT Realtime 2.1 mini (cheaper)',
                    'gpt-realtime-2' => 'GPT Realtime 2',
                    'gpt-realtime' => 'GPT Realtime',
                ], help: 'The mini model costs less, but follows instructions and calls tools less well.'),
                $this->text('openai_realtime_transcription_model', 'Transcription model',
                    help: 'Writes down what the visitor says, for the chat log: gpt-4o-mini-transcribe, gpt-4o-transcribe or whisper-1.'),
            ])->summary(fn (): string => $this->voiceSummary()),
            SettingsCard::make('advanced', 'Models for background jobs', 'The models behind the app\'s own jobs, how much history agents see, and the PDF models.', Heroicon::OutlinedCog8Tooth, fn (): array => [
                $this->modelChoice('fast_model', 'Fast',
                    help: 'Short, frequent calls: conversation titles, topics, tone, guardrails, starters, translation and the eval judge. A small, cheap model.'),
                $this->modelChoice('smart_model', 'Smart',
                    help: 'Reading content: page summaries and tables while sources are trained.'),
                $this->modelChoice('vision_model', 'Vision',
                    help: 'Describes the images inside documents. Needs a model that reads images.'),
                $this->integer('history_messages', 'Messages of history', help: 'How many recent messages of a conversation the model sees on each turn.', min: 1, max: 50),
                $this->text('summary_model', 'Document summary model', help: 'A Mistral model, for summaries and PDF chat.'),
                $this->text('analysis_model', 'Document analysis model', help: 'A Mistral model, for summaries and PDF chat.'),
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

    /**
     * Adds one or more of a provider's chat models. The first provider is
     * picked as the modal opens, so its models are listed at once, and they
     * are listed again whenever another provider is picked. Add stays off
     * until a model is picked or typed.
     */
    private function addModelAction(): Action
    {
        return Action::make('addModel')
            ->label('Add a model')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add a model')
            ->modalDescription('Pick a connected provider, then one or more of its chat models.')
            ->schema([
                Select::make('source')
                    ->label('Provider')
                    ->options(fn (): array => $this->sources())
                    ->default(fn (): ?string => array_key_first($this->sources()))
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('models', []);
                        $set('model_id', null);
                    }),
                Callout::make(fn (Get $get): ?string => $this->listOf($get('source'))?->message($this->sourceLabel($get('source')), $this->sourceAddress($get('source'))))
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->warning()
                    ->visible(fn (Get $get): bool => filled($get('source')) && $this->listOf($get('source'))?->status !== ModelListStatus::Listed),
                Select::make('models')
                    ->label('Models')
                    ->placeholder('Pick one or more models')
                    ->helperText('Models agents can use already are greyed out.')
                    ->options(fn (Get $get): array => collect($this->listOf($get('source'))->models ?? [])
                        ->mapWithKeys(fn (RemoteModelData $model): array => [$model->id => $model->name === $model->id ? $model->id : "{$model->name} ({$model->id})"])
                        ->all())
                    ->disableOptionWhen(fn (string $value, Get $get): bool => in_array($value, $this->addedModels($get('source')), true))
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->live()
                    ->visible(fn (Get $get): bool => $this->listOf($get('source'))?->status === ModelListStatus::Listed),
                TextInput::make('model_id')
                    ->label('Model')
                    ->placeholder('For example llama3.1:8b')
                    ->helperText('The model\'s name exactly as your provider writes it.')
                    ->required()
                    ->maxLength(255)
                    ->live(debounce: 300)
                    ->visible(fn (Get $get): bool => ($list = $this->listOf($get('source'))) instanceof ModelListData && $list->keyAccepted() && $list->status !== ModelListStatus::Listed),
            ])
            ->modalSubmitActionLabel('Add')
            ->modalSubmitAction(fn (Action $action): Action => $action->disabled(fn (): bool => ! $this->hasModelToAdd()))
            ->action(function (array $data, Action $action): void {
                $provider = $this->providerOf((string) $data['source']);

                if (! $provider instanceof LlmProvider) {
                    $action->halt();

                    return;
                }

                $listed = collect($this->listOf((string) $data['source'])->models ?? [])->keyBy('id');
                $added = collect((array) ($data['models'] ?? $data['model_id'] ?? []))
                    ->map(fn (mixed $id): string => trim((string) $id))
                    ->filter()
                    ->unique()
                    ->map(fn (string $id): Llm => app(AddLlmModelAction::class)->execute($provider, $listed->get($id) ?? new RemoteModelData($id, $id)));

                $added->count() === 1
                    ? Notification::make()->success()->title("{$added->first()->descriptive_name} added")->body('Agents can pick it now.')->send()
                    : Notification::make()->success()->title("{$added->count()} models added")->body('Agents can pick them now.')->send();
            });
    }

    /** Whether the open "Add a model" form names a model to add yet. */
    private function hasModelToAdd(): bool
    {
        $data = $this->getMountedAction()?->getRawData() ?? [];

        return filled($data['models'] ?? null) || filled(trim((string) ($data['model_id'] ?? '')));
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

    /**
     * A Documents service: each provider, marked connected or not (one that
     * is not cannot be picked), and Off. There is no "Automatic": the list
     * shows the service in use. A new pick is checked with a real call as it
     * is saved, and refused when the call fails; an unchanged one is not
     * called again.
     *
     * @param  array<string, string>  $providers
     * @param  Closure(AiProvider): ConnectionCheckData  $check
     */
    private function documentChoice(string $property, string $label, array $providers, string $help, Closure $check): Select
    {
        $options = collect($providers)
            ->map(fn (string $name, string $value): string => $name.(AiProvider::from($value)->isConnected() ? ' (connected)' : ' (not connected)'))
            ->put('none', 'Off')
            ->all();

        return $this->choice($property, $label, $options, help: $help)
            ->selectablePlaceholder(false)
            ->formatStateUsing(fn (): string => $this->documentDriver($property))
            ->disableOptionWhen(fn (string $value): bool => AiProvider::tryFrom($value)?->isConnected() === false)
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($property, $check): void {
                $provider = AiProvider::tryFrom((string) $value);

                if (! $provider instanceof AiProvider) {
                    return;
                }

                if (! $provider->isConnected()) {
                    $fail("{$provider->label()} is not connected. Add its key in AI providers first.");

                    return;
                }

                if ($value === $this->documentDriver($property)) {
                    return;
                }

                $result = $this->documentChecks["{$property}:{$provider->value}"] ??= $check($provider);

                if (! $result->connected) {
                    $fail($result->message ?? "{$provider->label()} could not be reached.");

                    return;
                }

                // It works, with a catch: the free Azure tier reads 2 pages a file.
                if ($result->warning !== null) {
                    Notification::make()->warning()->title("{$provider->label()} works, with a catch")->body($result->warning)->persistent()->send();
                }
            });
    }

    /** The service a Documents choice uses now, with an unsaved "Automatic" resolved. */
    private function documentDriver(string $property): string
    {
        if ($property === 'document_parser') {
            return app(DocumentReaders::class)->driver();
        }

        $llm = app(DocumentLlm::class);

        return $llm->isAvailable() ? $llm->slug() : 'none';
    }

    /** The reader in use, and whether summaries and PDF chat are on, for the card's summary line. */
    private function documentsSummary(): string
    {
        $reader = match ($this->documentDriver('document_parser')) {
            'mistral' => 'Mistral OCR',
            'azure' => 'Azure Document Intelligence',
            default => 'off',
        };

        return "Reading: {$reader} · PDF chat: ".($this->documentDriver('document_llm') === 'none' ? 'off' : 'on');
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

    /** The voice provider in use and the default voice, or "off". */
    private function voiceSummary(): string
    {
        $provider = $this->resolvedLabel('voice', AiCapability::Voice, 'off');

        return $provider === 'off' ? $provider : $provider.' · '.RealtimeVoice::configured()->label();
    }

    private function embeddingSummary(): string
    {
        $provider = EmbeddingProvider::tryFrom((string) $this->valueInUse('embedding_provider'));
        $summary = $provider instanceof EmbeddingProvider
            ? $provider->label().' ('.config($provider->modelConfig()).')'
            : $this->shown($this->valueInUse('embedding_provider'));
        $reindex = $this->runningReindex();

        return $reindex instanceof Batch ? $summary.' · re-indexing, '.$reindex->progress().'% done' : $summary;
    }

    /**
     * How far the re-index got while it runs, or a warning when the last one
     * could not embed every part.
     */
    private function reindexCallout(): Callout
    {
        return Callout::make(fn (): string => $this->runningReindex() instanceof Batch
            ? 'Re-indexing: '.$this->latestReindex()?->progress().'% done'
            : 'The last re-index missed some documents')
            ->description(fn (): string => $this->runningReindex() instanceof Batch
                ? 'It runs in the background queue. Until it ends, agents may miss some documents.'
                : "{$this->latestReindex()?->failedJobs} of {$this->latestReindex()?->totalJobs} parts could not be embedded, often because the embedding service refused too many requests. Run \"Re-index documents\" again.")
            ->icon(fn (): Heroicon => $this->runningReindex() instanceof Batch ? Heroicon::OutlinedArrowPath : Heroicon::OutlinedExclamationTriangle)
            ->status(fn (): string => $this->runningReindex() instanceof Batch ? 'info' : 'warning')
            ->visible(fn (): bool => $this->runningReindex() instanceof Batch || $this->reindexMissedParts());
    }

    private function latestReindex(): ?Batch
    {
        if ($this->latestReindex === false) {
            $this->latestReindex = app(EmbeddingReindexStatus::class)->latest();
        }

        return $this->latestReindex;
    }

    private function runningReindex(): ?Batch
    {
        $batch = $this->latestReindex();

        return $batch instanceof Batch && EmbeddingReindexStatus::runs($batch) ? $batch : null;
    }

    private function reindexMissedParts(): bool
    {
        $batch = $this->latestReindex();

        return $batch instanceof Batch && $batch->finished() && ! $batch->cancelled() && $batch->failedJobs > 0;
    }

    private function embeddingChoice(): Field
    {
        $providers = collect(EmbeddingProvider::cases())
            ->mapWithKeys(fn (EmbeddingProvider $provider): array => [$provider->value => $provider->label().($provider->account()->isConnected() ? '' : ' (not connected)')])
            ->all();

        return $this->choice('embedding_provider', 'Service', $providers)
            ->live()
            ->helperText('It must return the vector size this install stores. Its provider must be connected.')
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
            ? $this->choice($property, 'Model', app(OpenRouterEmbeddingModels::class)->options(app(EmbeddingStorage::class)->dimensions()))->searchable()->live()
            : $this->text($property, 'Model')->live(onBlur: true);

        return $field
            ->helperText($listed
                ? 'OpenRouter\'s embedding models. "Test embeddings" checks that the saved one returns the size this install stores.'
                : 'The model\'s name exactly as the service writes it.')
            ->visible(fn (Get $get): bool => ($get('embedding_provider') ?: $this->defaultValue('embedding_provider')) === $provider->value);
    }

    /** Whether documents or Q&A questions are stored as vectors. */
    private function hasStoredVectors(): bool
    {
        return $this->hasStoredVectors ??= Chunk::query()->exists() || AgentQuestionAnswerQuestion::query()->whereNotNull('embedding')->exists();
    }

    /**
     * Whether these form values change the embedding service or model while
     * vectors are stored: then everything must be embedded again.
     *
     * @param  array<string, mixed>  $state
     */
    private function changesStoredVectors(array $state): bool
    {
        if (! $this->hasStoredVectors()) {
            return false;
        }

        $saved = $this->settings()->toArray();

        return AiSettings::embeddingFor($saved) !== AiSettings::embeddingFor(array_merge($saved, $this->valuesToSave($state)));
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
