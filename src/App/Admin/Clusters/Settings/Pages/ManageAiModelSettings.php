<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Forms\ModelPicker;
use App\Admin\Settings\Actions\ApplyRecommendedTaskModelsAction;
use App\Admin\Settings\Actions\ReindexEmbeddingsAction;
use App\Admin\Settings\Actions\RemoveLlmModelAction;
use App\Admin\Settings\AiProviderCheck;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\Data\ConnectionCheckData;
use App\Admin\Settings\Data\RerankerSetupData;
use App\Admin\Settings\DocumentServiceCheck;
use App\Admin\Settings\LlmTaskText;
use App\Admin\Settings\ModelLogo;
use App\Admin\Settings\OptionLabel;
use App\Admin\Settings\RerankerCheck;
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
use Domain\Llm\Actions\Catalog\CheckLlmListingAction;
use Domain\Llm\Actions\Catalog\EnsureLlmProviderAction;
use Domain\Llm\Actions\Catalog\SyncLlmFactsAction;
use Domain\Llm\Contracts\Speaker;
use Domain\Llm\Data\Catalog\ModelFactsData;
use Domain\Llm\Data\Catalog\ModelListData;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Data\Speech\SpeechVoiceData;
use Domain\Llm\Enums\AiCapability;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Enums\ModelListStatus;
use Domain\Llm\Enums\RealtimeVoice;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\ChatFileModel;
use Domain\Llm\Services\ElevenLabsVoices;
use Domain\Llm\Services\ModelListing;
use Domain\Llm\Services\ModelPresets;
use Domain\Llm\Services\ModelsDevCatalog;
use Domain\Llm\Services\ProviderAdapters;
use Domain\Llm\Services\SourceSummaryModel;
use Domain\Llm\Services\TaskModels;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * What runs on the connected AI providers: the chat models agents can use
 * (added from a provider's list, removed with their agents moved to another
 * model) and the default for new agents; the embedding service; the
 * reranker; document reading; speech to text; voice. The models behind the
 * app's own tasks ({@see LlmTask}) are a closed card of their own: the model
 * for quick tasks, and one per task, grouped by when the task runs. "Use the
 * recommended models" sets them from the chat provider's preset
 * ({@see ApplyRecommendedTaskModelsAction}).
 *
 * The embedding service and its model decide the stored vectors, and a
 * service that cannot return the stored vector size is refused. Once
 * documents are stored as vectors, a change warns that they must be embedded
 * again and asks before it saves; saving starts that re-index in the queue
 * ({@see ReindexEmbeddingsAction}), and the card shows how far it got. "Re-index
 * documents" starts one by hand. A service whose provider is not connected
 * cannot be picked. The reranker, documents, speech and voice choices have
 * no "Automatic": they show the service in use, and a new pick is checked
 * with a real call as it is saved ({@see RerankerCheck},
 * {@see DocumentServiceCheck}, {@see AiProviderCheck}).
 */
class ManageAiModelSettings extends AdminSettingsPage implements HasTable
{
    use InteractsWithTable;

    private const string CHANGE_WARNING = 'Your documents were turned into vectors with the current model. Another model makes different vectors, so agents cannot find your documents until every document, Q&A and tool is embedded again. Saving starts this re-index in the background queue. With many documents it can take hours, and until it ends agents may miss documents.';

    private const string REINDEX_NOTE = 'Files are not read again: only the embeddings are made again.';

    /** The app's tasks on the "Models for app tasks" card, by when they run. */
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
     * The service checks made in this request: a save reads the form twice.
     *
     * @var array<string, ConnectionCheckData>
     */
    private array $serviceChecks = [];

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
                $this->unlistedCallout(),
                EmbeddedTable::make(),
                $this->withHelp($this->modelChoice('default_agent_model', 'Default model for new agents', unset: 'The first model on the list'),
                    'New agents answer with this model until they pick their own. The analytics assistant uses it too. Empty: the first model on the list.'),
                $this->withHelp($this->integer('history_messages', 'Messages of history', min: 1, max: 50),
                    'How many recent messages of a conversation an agent reads each time it answers, in typed chats and voice calls. More helps with follow-up questions, but each answer costs more.'),
                $this->toggle('allow_reasoning', 'Let agents reason',
                    'Models with "Reasons" on can think before they answer. With this on, each agent picks how hard in its AI model settings: Off, Low, Medium or High. Every agent starts at Off. Chatters see the thinking in grey above the answer, and can fold it away. More thinking gives better answers to hard questions, but they come slower and cost more. OpenAI\'s own API sends a summary of the thinking, once OpenAI has verified your organization. With this off, every agent is at Off.'),
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
                $this->serviceChoice('reranker', 'Reranker', [
                    'openrouter' => 'OpenRouter',
                    'cohere' => 'Cohere Rerank',
                    'custom' => 'Self-hosted',
                    'none' => 'Off (a basic similarity check)',
                ], 'OpenRouter and Cohere use their keys from AI providers.',
                    check: fn (string $driver, Get $get): ConnectionCheckData => app(RerankerCheck::class)->check($this->rerankerPicked($driver, $get)),
                    changed: fn (string $driver, Get $get): bool => $this->rerankerPicked($driver, $get)->toArray() !== $this->rerankerInUse()->toArray())
                    ->live(),
                $this->choice('openrouter_rerank_model', 'OpenRouter model', $openRouterRerankers = app(RerankModelCatalog::class)->openRouterOptions(),
                    help: 'The strongest first. Each model has its own scores, so its own relevance bar.')
                    ->options(ModelLogo::labels($openRouterRerankers, LlmProviderKind::OpenRouter))
                    ->allowHtml()
                    ->searchable()
                    ->visible(fn (Get $get): bool => $get('reranker') === 'openrouter'),
                $this->choice('cohere_rerank_model', 'Cohere model', $cohereRerankers = app(RerankModelCatalog::class)->cohereOptions())
                    ->options(collect($cohereRerankers)->map(fn (string $name): string => ModelLogo::forLab($name, 'cohere'))->all())
                    ->native(false)
                    ->allowHtml()
                    ->visible(fn (Get $get): bool => $get('reranker') === 'cohere'),
                $this->url('rerank_custom_url', 'Address',
                    help: 'A server with the common /rerank API (vLLM, Infinity, Jina-compatible), such as http://reranker:8000/v1.')
                    ->visible(fn (Get $get): bool => $get('reranker') === 'custom'),
                $this->secret('rerank_custom_key', 'API key', help: 'Only when your server asks for one.', required: false)
                    ->visible(fn (Get $get): bool => $get('reranker') === 'custom'),
                $this->text('rerank_custom_model', 'Model', help: 'The model name your server serves, such as Qwen/Qwen3-Reranker-4B.')
                    ->visible(fn (Get $get): bool => $get('reranker') === 'custom'),
            ])->summary(fn (): string => $this->rerankerSummary()),
            SettingsCard::make('documents', 'Documents', 'How PDFs, office files and images are read.', Heroicon::OutlinedDocumentMagnifyingGlass, fn (): array => [
                $this->serviceChoice('document_parser', 'Document reading', [
                    'mistral' => 'Mistral OCR',
                    'azure' => 'Azure Document Intelligence',
                    'none' => 'Off',
                ], 'Reads the text, scans and tables of PDFs, Word, slides and images while sources are trained. Mistral OCR also reads the pictures inside Word and PowerPoint files; Azure skips them. Excel, CSV, old office files and RTF are read by the app. Off: PDF, Word, slides and images cannot be uploaded.',
                    check: fn (string $reader): ConnectionCheckData => app(DocumentServiceCheck::class)->reader(AiProvider::from($reader))),
                $this->toggle('source_summaries_enabled', 'Source summaries',
                    help: 'A model reads each source whole, as a PDF file, and writes a short summary of it. It also finds the source\'s type, language and likely questions. Two calls per source, while AI enhancement is on (Settings → Knowledge). Off: no summaries; sources are still found by their pages.')
                    ->live(),
                $this->modelChoice('source_summaries_model', 'Model for source summaries', unset: fn (): string => $this->automaticLabel(app(SourceSummaryModel::class)->automatic()), only: fn (): array => $this->pdfModels('source_summaries_model'))
                    ->live()
                    ->helperText(fn (Get $get): string => $this->summaryModelHelp($get('source_summaries_model')))
                    ->visible(fn (Get $get): bool => (bool) $get('source_summaries_enabled')),
                Callout::make('No model reads PDF files')
                    ->description('Sources get no summary until a model that reads PDF files is added under Chat models.')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->warning()
                    ->visible(fn (Get $get): bool => (bool) $get('source_summaries_enabled') && $this->pdfModels('source_summaries_model') === []),
                $this->toggle('describe_images', 'Describe images',
                    help: 'A model describes the pictures inside PDF files and other documents, so search can find what they show. It runs while AI enhancement is on (Settings → Knowledge). The setup turns this off when your AI provider has no model that reads images. Off: pictures are skipped, and the text is still read.')
                    ->live(),
                $this->modelChoice('images_model', 'Model for image descriptions', help: 'Only the models that read images are listed.', unset: 'Same as the model for quick tasks', only: fn (): array => $this->imageModels('images_model'))
                    ->visible(fn (Get $get): bool => (bool) $get('describe_images')),
                Callout::make('No model reads images')
                    ->description('Pictures are not described until a model that reads images is added under Chat models.')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->warning()
                    ->visible(fn (Get $get): bool => (bool) $get('describe_images') && $this->imageModels('images_model') === []),
                $this->toggle('chat_files_enabled', 'Chat file uploads',
                    help: 'Lets chatters attach a PDF file or an image in a chat and ask about it, when the agent allows uploads too (agent Setup → Settings → Composer options). Off: no agent can offer uploads.')
                    ->live(),
                $this->modelChoice('chat_files_model', 'Model for chat file uploads', unset: fn (): string => $this->automaticLabel(app(ChatFileModel::class)->automatic()), only: fn (): array => $this->fileModels('chat_files_model'))
                    ->live()
                    ->helperText(fn (Get $get): string => $this->chatFilesHelp($get('chat_files_model')))
                    ->visible(fn (Get $get): bool => (bool) $get('chat_files_enabled')),
                Callout::make('No model reads PDF files and images')
                    ->description('Chatters cannot attach files until a model that reads both is added under Chat models.')
                    ->icon(Heroicon::OutlinedPaperClip)
                    ->warning()
                    ->visible(fn (Get $get): bool => (bool) $get('chat_files_enabled') && $this->fileModels('chat_files_model') === []),
            ])->advancedFields(fn (): array => [
                $this->text('mistral_ocr_model', 'Mistral OCR model'),
            ])->summary(fn (): string => $this->documentsSummary()),
            SettingsCard::make('speech', 'Speech to text', 'Voice input, and audio and video sources.', Heroicon::OutlinedMicrophone, fn (): array => [
                $this->serviceChoice('transcriber', 'Speech to text', [
                    'scaleway' => 'Scaleway AI Whisper',
                    'openai' => 'OpenAI Whisper',
                    'none' => 'Off',
                ], 'Off hides audio and video files from the sources.',
                    check: fn (string $provider): ConnectionCheckData => app(AiProviderCheck::class)->check(AiProvider::from($provider))),
            ])->advancedFields(fn (): array => [
                $this->text('scaleway_transcription_model', 'Scaleway AI model'),
                $this->text('openai_transcription_model', 'OpenAI model',
                    help: 'whisper-1, or gpt-4o-transcribe. Files up to 25 MB, about an hour and a half of audio.'),
            ])->summary(fn (): string => $this->providerInUse('transcriber')),
            SettingsCard::make('voice', 'Voice conversations', 'Live spoken calls with an agent, in the hosted chat and the chat widget.', Heroicon::OutlinedSpeakerWave, fn (): array => [
                $this->serviceChoice('voice', 'Voice conversations', [
                    'openai' => 'OpenAI Realtime',
                    'none' => 'Off',
                ], 'Each agent still turns them on (Setup → Settings → Composer options). A call costs much more than a typed chat.',
                    check: fn (string $provider): ConnectionCheckData => app(AiProviderCheck::class)->check(AiProvider::from($provider))),
                $this->choice('openai_realtime_voice', 'Default voice', collect(RealtimeVoice::cases())->mapWithKeys(fn (RealtimeVoice $voice): array => [$voice->value => $voice->label()])->all(),
                    help: 'Agents speak with it unless they pick their own. Marin and Cedar sound the most natural.'),
                $this->integer('voice_max_call_minutes', 'Longest call, in minutes', help: 'The agent says goodbye and hangs up then.', min: 1, max: 25),
            ])->advancedFields(fn (): array => [
                $this->choice('openai_realtime_model', 'Model', $realtimeModels = [
                    'gpt-realtime-2.1' => 'GPT Realtime 2.1',
                    'gpt-realtime-2.1-mini' => 'GPT Realtime 2.1 mini (cheaper)',
                    'gpt-realtime-2' => 'GPT Realtime 2',
                    'gpt-realtime' => 'GPT Realtime',
                ], help: 'The mini model costs less, but follows instructions and calls tools less well.')
                    ->options(ModelLogo::labels($realtimeModels, LlmProviderKind::OpenAi))
                    ->native(false)
                    ->allowHtml(),
                $this->text('openai_realtime_transcription_model', 'Transcription model',
                    help: 'Writes down what the visitor says, for the chat log: gpt-4o-mini-transcribe, gpt-4o-transcribe or whisper-1.'),
            ])->summary(fn (): string => $this->voiceSummary()),
            SettingsCard::make('read_aloud', 'Read aloud', 'Agent replies played as speech in the hosted chat.', Heroicon::OutlinedSpeakerWave, fn (): array => [
                $this->serviceChoice('speaker', 'Read aloud', [
                    'elevenlabs' => 'ElevenLabs',
                    'openai' => 'OpenAI Realtime',
                    'none' => 'Off',
                ], 'Each agent still turns it on (Setup → Settings → Composer options). A reply is read once; playing it again costs nothing.',
                    check: fn (string $provider): ConnectionCheckData => app(AiProviderCheck::class)->check(AiProvider::from($provider)))
                    ->live(),
                Callout::make('OpenAI reads with its realtime model')
                    ->description('It talks rather than reads, so each reading is checked against the reply and read again once when the words differ. ElevenLabs reads the text as it is.')
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->info()
                    ->visible(fn (Get $get): bool => $get('speaker') === 'openai'),
                $this->choice('openai_speech_voice', 'Default voice', collect(RealtimeVoice::cases())->mapWithKeys(fn (RealtimeVoice $voice): array => [$voice->value => $voice->label()])->all(),
                    help: 'Agents read with it unless they pick their own. Marin and Cedar sound the most natural.')
                    ->visible(fn (Get $get): bool => $get('speaker') === 'openai'),
                $this->choice('elevenlabs_speech_voice', 'Default voice', collect(app(ElevenLabsVoices::class)->all())->mapWithKeys(fn (SpeechVoiceData $voice): array => [$voice->id => $voice->label])->all(),
                    help: 'Any voice of your ElevenLabs account. Agents read with it unless they pick their own; they choose from a few professional voices that speak many languages. Empty uses the first of those.')
                    ->searchable()
                    ->visible(fn (Get $get): bool => $get('speaker') === 'elevenlabs'),
            ])->advancedFields(fn (): array => [
                $this->choice('openai_speech_model', 'OpenAI model', [
                    'gpt-realtime-2.1-mini' => 'GPT Realtime 2.1 mini',
                    'gpt-realtime-2.1' => 'GPT Realtime 2.1 (costs more)',
                ]),
                $this->choice('elevenlabs_speech_model', 'ElevenLabs model', [
                    'eleven_flash_v2_5' => 'Flash v2.5 (fast, cheapest, 32 languages)',
                    'eleven_v4_turbo' => 'v4 Turbo (fast, 90+ languages)',
                    'eleven_v4' => 'v4 (best quality, 90+ languages)',
                    'eleven_multilingual_v2' => 'Multilingual v2 (29 languages)',
                ]),
            ])->summary(fn (): string => $this->readAloudSummary()),
            SettingsCard::make('tasks', 'Models for app tasks', 'The models behind the app\'s own AI calls. Empty uses the default model.', Heroicon::OutlinedCog8Tooth, fn (): array => [
                Actions::make([$this->recommendedTaskModelsAction()])->key('recommended-task-models'),
                $this->withHelp($this->modelChoice('task_model', 'Model for quick tasks'),
                    'Every task below without its own model uses this one: guardrails, follow-up questions, titles and other small jobs. A small, fast model suits them. Empty: the default model for new agents.'),
                ...array_map(fn (string $group, array $tasks): Fieldset => Fieldset::make($group)
                    ->columns(1)
                    ->schema(array_map($this->taskChoice(...), $tasks)), array_keys(LlmTaskText::GROUPS), LlmTaskText::GROUPS),
            ])->properties('task_model', ...array_map(fn (LlmTask $task): string => $task->settingsProperty(), LlmTaskText::listedTasks()))
                ->summary(fn (): string => $this->tasksSummary())
                ->advanced(),
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
                ->orderBy('name'))
            ->columns([
                TextColumn::make('name')
                    ->label('Model')
                    ->formatStateUsing(fn (Llm $model): HtmlString => new HtmlString(ModelLogo::forLlm($model)))
                    ->suffix(fn (Llm $model): string => (string) $model->id === (string) $this->valueInUse('default_agent_model') ? ' · default' : '')
                    ->description(fn (Llm $model): ?string => match (true) {
                        $model->unlisted_at !== null => 'No longer served by its provider',
                        ! $model->callsTools() => 'No tool calling: tools will not work',
                        default => null,
                    }),
                TextColumn::make('provider.name')
                    ->label('Provider')
                    ->description(fn (Llm $model): ?string => $model->provider->is_enabled && $model->provider->isConnected() ? null : 'Not connected')
                    ->color(fn (Llm $model): string => $model->provider->is_enabled && $model->provider->isConnected() ? 'gray' : 'warning'),
                // Whether the model may reason. The catalog says whether a
                // model it lists can, so the switch locks off for one that
                // cannot; for any other model the admin also says whether it can.
                ToggleColumn::make('reasons')
                    ->label('Reasons')
                    ->getStateUsing(fn (Llm $record): bool => $record->reasons())
                    ->updateStateUsing(fn (Llm $record, bool $state) => $record->update($this->listedOnModelsDev($record)
                        ? ['is_reasoning_enabled' => $state]
                        : ['is_reasoning_enabled' => $state, 'capabilities' => [...(array) $record->capabilities, 'reasoning' => $state]]))
                    ->disabled(fn (Llm $record): bool => $this->cannotReason($record))
                    ->tooltip(fn (Llm $record): ?string => $this->cannotReason($record)
                        ? null
                        : 'On: the model may think before it answers, and agents can set how hard. Off: it thinks as little as it can.')
                    // A disabled switch takes no pointer events, so its own
                    // tooltip never opens: the locked one sits on the wrapper.
                    ->extraAttributes(fn (Llm $record): array => $this->cannotReason($record)
                        ? ['x-tooltip' => '{ content: '.Js::from('This model cannot reason, says models.dev.').', theme: $store.theme }']
                        : []),
                TextColumn::make('agents_count')
                    ->label('Agents'),
            ])
            ->headerActions([$this->refreshFactsAction(), $this->addModelAction()])
            ->recordActions([$this->removeModelAction()])
            ->emptyStateHeading('No models yet')
            ->emptyStateDescription('Connect a provider in AI providers, then add its models here.')
            ->emptyStateIcon(Heroicon::OutlinedChatBubbleLeftRight)
            ->paginated(false);
    }

    /**
     * Adds one or more of a provider's chat models. Every chat provider is
     * listed, the ones without a key greyed out; the first
     * connected one is picked as the modal opens, so its models are listed at
     * once to tick, and they are listed again whenever another provider is
     * picked. Add stays off until a model is ticked or typed.
     */
    private function addModelAction(): Action
    {
        return Action::make('addModel')
            ->label('Add a model')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add a model')
            ->modalDescription('Pick a provider, then tick the chat models agents can use.')
            ->modalWidth(Width::TwoExtraLarge)
            ->schema([
                Select::make('source')
                    ->label('Provider')
                    ->helperText('Providers without a key are greyed out: add it in AI providers.')
                    ->options(fn (): array => collect($this->sources())
                        ->map(fn (string $name, string $source): string => ModelLogo::forProvider(
                            $this->isConnected($source) ? $name : "{$name} (no key yet)",
                            $this->draftOf($source)->kind ?? LlmProviderKind::Custom,
                        ))
                        ->all())
                    ->allowHtml()
                    ->disableOptionWhen(fn (string $value): bool => ! $this->isConnected($value))
                    ->default(fn (): ?string => collect($this->sources())->keys()->first(fn (string $source): bool => $this->isConnected($source)))
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
                    ->visible(fn (Get $get): bool => filled($get('source')) && $this->listOf($get('source'))?->status !== ModelListStatus::Listed && ! $this->typesModelNames($get('source'))),
                ModelPicker::make('models')
                    ->label('Models')
                    ->options(function (Get $get): array {
                        $kind = $this->draftOf($get('source'))->kind ?? LlmProviderKind::Custom;

                        return collect($this->listOf($get('source'))->models ?? [])
                            ->mapWithKeys(fn (RemoteModelData $model): array => [$model->id => ModelLogo::label($model->name, $kind, $model->id)])
                            ->all();
                    })
                    ->descriptions(fn (Get $get): array => $this->modelDescriptions($get('source')))
                    ->disableOptionWhen(fn (string $value, Get $get): bool => in_array($value, $this->addedModels($get('source')), true))
                    ->required()
                    ->validationMessages(['required' => 'Tick at least one model.'])
                    ->live()
                    ->visible(fn (Get $get): bool => $this->listOf($get('source'))?->status === ModelListStatus::Listed),
                Callout::make(fn (Get $get): string => Arr::join($this->pickedWithoutTools($get), ', ', ' and ').' cannot call tools')
                    ->description(fn (Get $get): string => count($this->pickedWithoutTools($get)) === 1
                        ? 'Tools will not work for agents that use it: they answer without them. You can still add it.'
                        : 'Tools will not work for agents that use them: they answer without them. You can still add them.')
                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                    ->warning()
                    ->visible(fn (Get $get): bool => $this->pickedWithoutTools($get) !== []),
                TextInput::make('model_id')
                    ->label('Model')
                    ->placeholder(fn (Get $get): string => $this->typesModelNames($get('source')) ? 'For example gpt-4.1' : 'For example llama3.1:8b')
                    ->helperText(fn (Get $get): string => $this->modelNameHelp($get('source')) ?? 'The model\'s name exactly as your provider writes it.')
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
                    ->map(fn (mixed $id): string => Str::trim((string) $id))
                    ->filter()
                    ->unique()
                    ->map(fn (string $id): Llm => app(AddLlmModelAction::class)->execute($provider, $listed->get($id) ?? new RemoteModelData($id, $id)));

                $added->count() === 1
                    ? Notification::make()->success()->title("{$added->first()->name} added")->body('Agents can pick it now.')->send()
                    : Notification::make()->success()->title("{$added->count()} models added")->body('Agents can pick them now.')->send();
            });
    }

    /**
     * The models picked in "Add a model" that models.dev says cannot call
     * tools, by id.
     *
     * @return list<string>
     */
    private function pickedWithoutTools(Get $get): array
    {
        $draft = $this->draftOf($get('source'));

        if (! $draft instanceof LlmProvider) {
            return [];
        }

        $catalog = app(ModelsDevCatalog::class);

        return collect((array) ($get('models') ?? []))
            ->map(fn (mixed $id): string => (string) $id)
            ->filter(fn (string $id): bool => $catalog->facts($draft->kind, $id)?->callsTools === false)
            ->values()
            ->all();
    }

    /** Whether the open "Add a model" form names a model to add yet. */
    private function hasModelToAdd(): bool
    {
        $data = $this->getMountedAction()?->getRawData() ?? [];

        return filled($data['models'] ?? null) || filled(Str::trim((string) ($data['model_id'] ?? '')));
    }

    /** Whether models.dev lists the model, so its capabilities come from there. */
    private function listedOnModelsDev(Llm $record): bool
    {
        return app(ModelsDevCatalog::class)->entry($record->provider->kind, $record->name) !== null;
    }

    /** Whether models.dev lists the model and says it cannot reason. */
    private function cannotReason(Llm $record): bool
    {
        return ! $record->canReason() && $this->listedOnModelsDev($record);
    }

    /**
     * Reads each model's details from models.dev again now, instead of
     * waiting for the daily sync.
     */
    private function refreshFactsAction(): Action
    {
        return Action::make('refreshFacts')
            ->label('Refresh model details')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->action(function (): void {
                $result = app(SyncLlmFactsAction::class)->execute();

                Notification::make()->success()
                    ->title('Model details refreshed')
                    ->body("{$result->updated} updated. {$result->notListed} not listed on models.dev: their \"Reasons\" switch decides.")
                    ->send();
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
            ->modalHeading(fn (Llm $record): string => "Remove {$record->name}?")
            ->modalDescription(fn (Llm $record): string => ($agents = $this->agentsUsing($record)) > 0
                ? "{$agents} ".Str::plural('agent', $agents).' answer with it. Pick the model they use from now on.'
                : 'Agents can no longer pick it. You can add it again later.')
            ->schema(fn (Llm $record): array => $this->agentsUsing($record) === 0 ? [] : [
                Select::make('replacement')
                    ->label('Model for these agents')
                    ->options($this->replacements($record))
                    ->allowHtml()
                    ->default(fn (): ?int => $this->defaultChatModel()?->getKey())
                    ->required()
                    ->native(false),
            ])
            ->modalSubmitActionLabel('Remove')
            ->action(function (array $data, Llm $record): void {
                app(RemoveLlmModelAction::class)->execute($record, isset($data['replacement']) ? Llm::query()->find($data['replacement']) : null);
                $this->fillCards();

                Notification::make()->success()->title("{$record->name} removed")->send();
            });
    }

    /**
     * The providers a model can be added from: every account with chat
     * models, connected or not, then the servers of your own.
     *
     * @return array<string, string>
     */
    private function sources(): array
    {
        $accounts = collect(AiProvider::cases())
            ->filter(fn (AiProvider $provider): bool => $provider->can(AiCapability::Chat) && $provider->chatKind() instanceof LlmProviderKind)
            ->mapWithKeys(fn (AiProvider $provider): array => ["account:{$provider->value}" => $provider->label()]);
        $servers = LlmProvider::query()->where('kind', LlmProviderKind::Custom)->where('is_enabled', true)->orderBy('id')->get()
            ->mapWithKeys(fn (LlmProvider $server): array => ["server:{$server->id}" => $server->name]);

        return $accounts->merge($servers)->all();
    }

    /** Whether models can be added from a source: an account with its key, or a server of your own. */
    private function isConnected(string $source): bool
    {
        [$type, $value] = array_pad(explode(':', $source, 2), 2, '');

        return match ($type) {
            'account' => AiProvider::tryFrom($value)?->isConnected() ?? false,
            'server' => true,
            default => false,
        };
    }

    /**
     * Under each listed model: its id, when its name differs, that agents can
     * use it already, and that tools do not work with it.
     *
     * @return array<string, string>
     */
    private function modelDescriptions(mixed $source): array
    {
        $draft = $this->draftOf($source);
        $added = $this->addedModels($source);
        $catalog = app(ModelsDevCatalog::class);

        return collect($this->listOf($source)->models ?? [])
            ->mapWithKeys(fn (RemoteModelData $model): array => [$model->id => collect([
                $model->name === $model->id ? null : $model->id,
                in_array($model->id, $added, true) ? 'Agents can use it already' : null,
                $draft instanceof LlmProvider && $catalog->facts($draft->kind, $model->id)?->callsTools === false ? 'No tool calling: tools will not work' : null,
            ])->filter()->implode(' · ')])
            ->filter()
            ->all();
    }

    /** How to name a model of a source whose models are typed, not listed (Azure deployments). */
    private function modelNameHelp(mixed $source): ?string
    {
        $draft = $this->draftOf($source);

        return $draft instanceof LlmProvider ? app(ProviderAdapters::class)->for($draft->kind)->modelNameHelp() : null;
    }

    /** Whether a source's models are typed by name: an empty list is then no warning. */
    private function typesModelNames(mixed $source): bool
    {
        return $this->modelNameHelp($source) !== null && $this->listOf($source)?->status === ModelListStatus::Empty;
    }

    /** The provider row a source names: the account's (made when missing) or a server of your own. */
    private function providerOf(string $source): ?LlmProvider
    {
        [$type, $value] = array_pad(explode(':', $source, 2), 2, '');

        return match ($type) {
            'account' => ($kind = AiProvider::tryFrom($value)?->chatKind()) instanceof LlmProviderKind && AiProvider::from($value)->isConnected()
                ? app(EnsureLlmProviderAction::class)->execute($kind)
                : null,
            'server' => LlmProvider::query()->where('kind', LlmProviderKind::Custom)->where('is_enabled', true)->find((int) $value),
            default => null,
        };
    }

    /** An unsaved row to read a source's list with, without making the account's row yet. */
    private function draftOf(mixed $source): ?LlmProvider
    {
        [$type, $value] = array_pad(explode(':', (string) $source, 2), 2, '');

        return match ($type) {
            'account' => ($kind = AiProvider::tryFrom($value)?->chatKind()) instanceof LlmProviderKind
                ? new LlmProvider(['kind' => $kind, 'name' => AiProvider::from($value)->label()])
                : null,
            'server' => LlmProvider::query()->where('kind', LlmProviderKind::Custom)->find((int) $value),
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
            ->pluck('name')
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
            ->mapWithKeys(fn (Llm $other): array => [$other->id => ModelLogo::forLlm($other, "{$other->name} ({$other->provider->name})")])
            ->all();
    }

    /**
     * The models a provider stopped serving ({@see CheckLlmListingAction}):
     * hidden from the pickers, and the app's own tasks that used one use
     * another model until it is served again or replaced.
     */
    private function unlistedCallout(): Callout
    {
        $unlisted = fn (): Collection => Llm::catalog()->filter(fn (Llm $llm): bool => $llm->is_enabled && $llm->unlisted_at !== null);

        return Callout::make(fn (): string => Arr::join($unlisted()->map(fn (Llm $llm): string => $llm->name.' ('.$llm->provider->name.')')->all(), ', ', ' and ')
            .(count($unlisted()) === 1 ? ' is' : ' are').' no longer served')
            ->description('The provider refused calls to it and no longer lists it, so the pickers hide it, and the app\'s own tasks that used it now use another model. Agents that use it cannot answer. Pick another model where it is still set. It comes back on its own once the provider serves it again.')
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->warning()
            ->visible(fn (): bool => $unlisted()->isNotEmpty());
    }

    private function modelsSummary(): string
    {
        $count = Llm::available()->count();

        if ($count === 0) {
            return 'No models yet';
        }

        $default = $this->catalogName($this->valueInUse('default_agent_model')) ?? Llm::available()->first()?->name;

        return $count.' '.Str::plural('model', $count).' · default: '.$default;
    }

    /**
     * A catalog model, by its id, for one of the app's jobs. Unset uses
     * the default model; $unset is the placeholder that says so.
     */
    private function modelChoice(string $property, string $label, ?string $help = null, string|Closure $unset = 'Same as the default model', ?Closure $only = null): Select
    {
        $options = fn (): array => $this->catalogOptions($only instanceof Closure ? $only() : null);

        return Select::make($property)
            ->label($label)
            ->helperText($help)
            ->options($options)
            ->allowHtml()
            ->in(fn (): array => array_merge(...array_map(array_keys(...), array_values($options()))))
            ->searchable()
            ->placeholder($unset);
    }

    // --- Models for app tasks ----------------------------------------------------

    /** One task's model, with a "?" that says what uses it and which model fits. */
    private function taskChoice(LlmTask $task): Select
    {
        return $this->withHelp($this->modelChoice(
            $task->settingsProperty(),
            LlmTaskText::label($task),
            unset: 'Same as the model for quick tasks',
        ), LlmTaskText::help($task));
    }

    /**
     * The ids of the models that read both PDF files and images, so they can
     * answer about a file a chatter attaches. The model in use stays listed.
     *
     * @return list<int>
     */
    private function fileModels(string $property): array
    {
        $files = app(ChatFileModel::class);
        $ids = Llm::available()->filter(fn (Llm $llm): bool => $files->readsFiles($llm))->pluck('id')->all();
        $inUse = Llm::fromSetting($this->valueInUse($property))?->id;

        return array_values(array_unique($inUse !== null ? [...$ids, $inUse] : $ids));
    }

    /**
     * The help under "Model for chat file uploads": what it does, and, while
     * none is picked, how Automatic chooses.
     */
    private function chatFilesHelp(mixed $picked): string
    {
        $does = 'Answers about the PDF file or image a chatter attaches when the agent\'s own model cannot read files; an agent whose model reads both answers itself. Only the models that read both are listed.';

        if (Llm::fromSetting($picked) instanceof Llm) {
            return $does;
        }

        return app(ChatFileModel::class)->automatic() instanceof Llm
            ? "{$does} Automatic takes the default model if it reads both, else the first one that does."
            : "{$does} Automatic: no model reads both, so uploads are off.";
    }

    /** The empty choice of a model picker that chooses for itself, with the model it takes now. */
    private function automaticLabel(?Llm $model): string
    {
        return $model instanceof Llm ? "Automatic ({$model->name})" : 'Automatic (no model)';
    }

    /**
     * The ids of the models that read PDF files, so they can summarise a
     * whole source. The model in use stays listed.
     *
     * @return list<int>
     */
    private function pdfModels(string $property): array
    {
        $summaries = app(SourceSummaryModel::class);
        $ids = Llm::available()->filter(fn (Llm $llm): bool => $summaries->readsPdf($llm))->pluck('id')->all();
        $inUse = Llm::fromSetting($this->valueInUse($property))?->id;

        return array_values(array_unique($inUse !== null ? [...$ids, $inUse] : $ids));
    }

    /**
     * The help under "Model for source summaries": what it does, and, while
     * none is picked, how Automatic chooses.
     */
    private function summaryModelHelp(mixed $picked): string
    {
        $does = 'Reads each source whole and writes its summary. Only the models that read PDF files are listed.';

        if (Llm::fromSetting($picked) instanceof Llm) {
            return $does;
        }

        return app(SourceSummaryModel::class)->automatic() instanceof Llm
            ? "{$does} Automatic takes the default model if it reads PDF files, else the first one that does."
            : "{$does} Automatic: no model reads PDF files, so there are no summaries.";
    }

    /**
     * The ids of the models that can describe images: the ones known to read
     * them (models.dev), or, when none is known to, every one not known not
     * to. The model in use stays listed.
     *
     * @return list<int>
     */
    private function imageModels(string $property): array
    {
        $reads = Llm::available()->mapWithKeys(fn (Llm $llm): array => [$llm->id => $this->readsImages($llm)]);
        $readers = $reads->filter(fn (?bool $reads): bool => $reads === true);
        $ids = ($readers->isNotEmpty() ? $readers : $reads->reject(fn (?bool $reads): bool => $reads === false))->keys()->all();
        $inUse = Llm::fromSetting($this->valueInUse($property))?->id;

        return array_values(array_unique($inUse !== null ? [...$ids, $inUse] : $ids));
    }

    /**
     * Sets the task models from the preset of the default model's provider:
     * its small model for quick tasks, the default model for the tasks that
     * need a large one, and a model that reads images for image
     * descriptions (with none, image descriptions are turned off). Hidden when that provider has no preset (a server of
     * your own). Its list is read as the modal opens, to name the model.
     */
    private function recommendedTaskModelsAction(): Action
    {
        return Action::make('recommendedTaskModels')
            ->label('Use the recommended models')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->visible(fn (): bool => $this->presetAccount() instanceof AiProvider)
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedSparkles)
            ->modalHeading('Use the recommended models?')
            ->modalDescription(function (): string {
                $small = $this->recommendedQuickTaskModel();
                $vision = $this->recommendedVisionModel();

                if (! $small instanceof RemoteModelData) {
                    return "{$this->presetAccount()?->label()} lists none of the recommended models for quick tasks now.";
                }

                return "Picks {$small->name} for quick tasks, and the default model for the evaluation judge and translations. "
                    .($vision instanceof RemoteModelData
                        ? "{$vision->name} describes the pictures inside documents. "
                        : 'None of its models reads images, so image descriptions are turned off. ')
                    .'The models you set for single tasks are cleared.';
            })
            ->modalSubmitActionLabel('Use these models')
            ->action(function (): void {
                $account = $this->presetAccount();
                $small = $this->recommendedQuickTaskModel();
                $chat = $this->defaultChatModel();

                if (! $account instanceof AiProvider || ! $small instanceof RemoteModelData || ! $chat instanceof Llm) {
                    Notification::make()->warning()->title('No recommended model is listed')->body('Pick the model for quick tasks below.')->send();

                    return;
                }

                $model = app(ApplyRecommendedTaskModelsAction::class)->execute($chat->provider, $small, $chat, $this->recommendedVisionModel());
                $this->fillCards();

                Notification::make()->success()->title("{$model->name} does the quick tasks now")->send();
            });
    }

    /** The default model for new agents, while it may be used. */
    private function defaultChatModel(): ?Llm
    {
        $model = Llm::fromSetting($this->valueInUse('default_agent_model'));

        return $model?->isAvailable() ? $model : null;
    }

    /** The account of the default model, when it has a model for quick tasks to recommend. */
    private function presetAccount(): ?AiProvider
    {
        $kind = $this->defaultChatModel()?->provider->kind;
        $account = $kind instanceof LlmProviderKind ? AiProvider::forChatKind($kind) : null;

        return $account?->presetQuickTaskModels() !== [] ? $account : null;
    }

    /** The first recommended model for quick tasks the account lists. */
    private function recommendedQuickTaskModel(): ?RemoteModelData
    {
        $account = $this->presetAccount();

        return $account instanceof AiProvider
            ? app(ModelPresets::class)->quickTaskModel($account, $this->listOf("account:{$account->value}")->models ?? [])
            : null;
    }

    /** The first recommended model for image descriptions the account lists. */
    private function recommendedVisionModel(): ?RemoteModelData
    {
        $account = $this->presetAccount();

        return $account instanceof AiProvider
            ? app(ModelPresets::class)->visionModel($account, $this->listOf("account:{$account->value}")->models ?? [])
            : null;
    }

    /** The model every task uses, and how many tasks have their own. */
    private function tasksSummary(): string
    {
        $shared = $this->catalogName($this->valueInUse('task_model'));
        $own = count(array_filter(LlmTaskText::listedTasks(), fn (LlmTask $task): bool => filled($this->valueInUse($task->settingsProperty()))));

        return ($shared ?? 'The default model').' for every task'
            .($own === 0 ? '' : ' · '.$own.' '.Str::plural('task', $own).' with '.($own === 1 ? 'its' : 'their').' own model');
    }

    /**
     * Whether a model reads images: as its own facts say, else as models.dev
     * says now (a model added before the facts kept its inputs); null when
     * neither says.
     */
    private function readsImages(Llm $llm): ?bool
    {
        if (($reads = $llm->readsImages()) !== null) {
            return $reads;
        }

        $entry = app(ModelsDevCatalog::class)->storedEntry($llm->provider->kind, $llm->name);

        return $entry !== null ? ModelFactsData::fromCatalog($entry)->readsImages : null;
    }

    /**
     * The models that may be picked (all, or the ids given), by provider:
     * id => name, with its logo.
     *
     * @param  list<int>|null  $ids
     * @return array<string, array<int, string>>
     */
    private function catalogOptions(?array $ids = null): array
    {
        return Llm::available()
            ->when($ids !== null, fn ($models) => $models->filter(fn (Llm $llm): bool => in_array($llm->id, $ids, true)))
            ->groupBy(fn (Llm $llm): string => $llm->provider->name)
            ->map(fn ($models): array => $models->mapWithKeys(fn (Llm $llm): array => [$llm->id => ModelLogo::forLlm($llm)])->all())
            ->all();
    }

    private function catalogName(mixed $value): ?string
    {
        return Llm::fromSetting($value)?->name;
    }

    // --- Services ----------------------------------------------------------------

    /**
     * A service choice (reranker, documents, speech, voice): each provider
     * by its product name, marked connected or not (one that is not cannot
     * be picked), with the choices that need no key (Self-hosted, Off). There
     * is no "Automatic": the list shows the service in use. A new pick is
     * checked with a real call as it is saved, and refused when the call
     * fails; an unchanged one is not called again.
     *
     * @param  array<string, string>  $options  The name of each choice, by stored value.
     * @param  Closure(string, Get): ConnectionCheckData  $check  The real call for a pick.
     * @param  (Closure(string, Get): bool)|null  $changed  Whether a pick differs from the one in use; by default, when it is another service.
     */
    private function serviceChoice(string $property, string $label, array $options, string $help, Closure $check, ?Closure $changed = null): Select
    {
        $changed ??= fn (string $value): bool => $value !== $this->driverInUse($property);
        $marked = collect($options)
            ->map(fn (string $name, string $value): string => ($provider = AiProvider::tryFrom($value)) instanceof AiProvider
                ? $name.($provider->isConnected() ? ' (connected)' : ' (not connected)')
                : $name)
            ->all();

        return $this->choice($property, $label, $marked, help: $help)
            ->options($this->serviceLabels($marked))
            ->native(false)
            ->allowHtml()
            ->selectablePlaceholder(false)
            ->formatStateUsing(fn (): string => $this->driverInUse($property))
            ->disableOptionWhen(fn (string $value): bool => AiProvider::tryFrom($value)?->isConnected() === false)
            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($property, $options, $check, $changed, $get): void {
                $value = (string) $value;
                $provider = AiProvider::tryFrom($value);

                if ($provider instanceof AiProvider && ! $provider->isConnected()) {
                    $fail("{$provider->label()} is not connected. Add its key in AI providers first.");

                    return;
                }

                // Off needs no call; a value that is not offered fails the "in" rule.
                if ($value === 'none' || ! array_key_exists($value, $options) || ! $changed($value, $get)) {
                    return;
                }

                $result = $this->serviceChecks["{$property}:{$value}"] ??= $check($value, $get);

                if (! $result->connected) {
                    $fail($result->message ?? "{$options[$value]} could not be reached.");

                    return;
                }

                // It works, with a catch: the free Azure tier reads 2 pages a file.
                if ($result->warning !== null) {
                    Notification::make()->warning()->title("{$options[$value]} works, with a catch")->body($result->warning)->persistent()->send();
                }
            });
    }

    /**
     * The names of a service choice's options as HTML: a provider with its
     * logo, Self-hosted and Off with an icon.
     *
     * @param  array<string, string>  $names  The name of each choice, by stored value.
     * @return array<string, string>
     */
    private function serviceLabels(array $names): array
    {
        return collect($names)
            ->map(fn (string $name, string $value): string => match (true) {
                ($provider = AiProvider::tryFrom($value)) instanceof AiProvider => OptionLabel::provider($name, $provider),
                $value === 'custom' => OptionLabel::icon($name, Heroicon::OutlinedServerStack),
                default => OptionLabel::icon($name, Heroicon::OutlinedNoSymbol),
            })
            ->all();
    }

    /**
     * The service a choice uses now: the one saved, or the one "Automatic"
     * (the default while nothing is saved) resolves to, as the app picks it.
     */
    private function driverInUse(string $property): string
    {
        if ($property === 'document_parser') {
            return app(DocumentReaders::class)->driver();
        }

        $driver = Str::lower(Str::trim((string) $this->configured($property)));

        if ($driver !== '' && $driver !== 'auto') {
            return $driver;
        }

        return match ($property) {
            'reranker' => app(RerankerFactory::class)->autoDriver(),
            'transcriber' => AiProvider::firstConnectedFor(AiCapability::Speech)?->value ?? 'none',
            'voice' => AiProvider::firstConnectedFor(AiCapability::Voice)?->value ?? 'none',
            'speaker' => AiProvider::firstConnectedFor(AiCapability::TextToSpeech)?->value ?? 'none',
            default => throw new InvalidArgumentException("{$property} is not a service choice."),
        };
    }

    /** A setting as the app reads it now: the saved value, else its default. */
    private function configured(string $property): mixed
    {
        return config(AiSettings::configKeys()[$property][0]->key);
    }

    /** The provider a service choice uses now, by name, or "off". */
    private function providerInUse(string $property): string
    {
        return AiProvider::tryFrom($this->driverInUse($property))?->label() ?? 'off';
    }

    /** The reader in use and the models for summaries, image descriptions and chat files, for the card's summary line. */
    private function documentsSummary(): string
    {
        $reader = match ($this->driverInUse('document_parser')) {
            'mistral' => 'Mistral OCR',
            'azure' => 'Azure Document Intelligence',
            default => 'off',
        };

        return "Reading: {$reader} · Summaries: ".(app(SourceSummaryModel::class)->find()->name ?? 'off')
            .' · Images: '.((bool) $this->valueInUse('describe_images') ? (app(TaskModels::class)->find(LlmTask::Images)->name ?? 'off') : 'off')
            .' · Chat uploads: '.(app(ChatFileModel::class)->find()->name ?? 'off');
    }

    /**
     * The reranker the form picks: a blank model is the default one, and a
     * blank key of your own server keeps the saved key.
     */
    private function rerankerPicked(string $driver, Get $get): RerankerSetupData
    {
        $key = $get('rerank_custom_key');

        return $this->rerankerSetup(
            $driver,
            fn (string $property): mixed => filled($get($property)) ? $get($property) : $this->defaultValue($property),
            filled($key) ? (string) $key : null,
        );
    }

    /** The reranker in use, with the saved key. */
    private function rerankerInUse(): RerankerSetupData
    {
        return $this->rerankerSetup($this->driverInUse('reranker'), $this->configured(...));
    }

    /**
     * A reranker: OpenRouter or Cohere with its model, or your own server
     * with its address, model and key (null keeps the saved one).
     *
     * @param  Closure(string): mixed  $value  An option's value, by property.
     */
    private function rerankerSetup(string $driver, Closure $value, ?string $key = null): RerankerSetupData
    {
        $text = function (string $property) use ($value): ?string {
            $option = $value($property);

            return filled($option) ? (string) $option : null;
        };

        return match ($driver) {
            'openrouter' => new RerankerSetupData('openrouter', $text('openrouter_rerank_model')),
            'cohere' => new RerankerSetupData('cohere', $text('cohere_rerank_model')),
            'custom' => new RerankerSetupData('custom', $text('rerank_custom_model'), $key, $text('rerank_custom_url')),
            default => new RerankerSetupData('none'),
        };
    }

    /** The reranker in use, for the card's summary line. */
    private function rerankerSummary(): string
    {
        $reranker = app(Reranker::class);

        return $reranker->isEnabled() ? $reranker->model() : 'Off: a basic similarity check';
    }

    /** The voice provider in use and the default voice, or "off". */
    private function voiceSummary(): string
    {
        $provider = $this->providerInUse('voice');

        return $provider === 'off' ? $provider : $provider.' · '.RealtimeVoice::configured()->label();
    }

    /** The service that reads replies aloud, with its default voice. */
    private function readAloudSummary(): string
    {
        $provider = $this->providerInUse('speaker');
        $speaker = app(Speaker::class);
        $voice = $speaker->defaultVoice();
        $name = array_find($speaker->voices(), fn (SpeechVoiceData $choice): bool => $choice->id === $voice)?->label;

        return $provider === 'off' || blank($name) ? $provider : $provider.' · '.$name;
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
            ->options(collect($providers)->map(fn (string $name, string $value): string => OptionLabel::provider($name, EmbeddingProvider::from($value)->account()))->all())
            ->native(false)
            ->allowHtml()
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
        $property = AiSettings::propertyFor($provider->modelConfig()) ?? '';
        $listed = $provider === EmbeddingProvider::OpenRouter;
        $field = $listed
            ? $this->choice($property, 'Model', $models = app(OpenRouterEmbeddingModels::class)->options(app(EmbeddingStorage::class)->dimensions()))
                ->options(ModelLogo::labels($models, LlmProviderKind::OpenRouter))
                ->allowHtml()
                ->searchable()
                ->live()
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
