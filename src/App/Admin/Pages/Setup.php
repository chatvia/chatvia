<?php

declare(strict_types=1);

namespace App\Admin\Pages;

use App\Admin\AdminPanelProvider;
use App\Admin\Forms\CardRadio;
use App\Admin\RequireSetup;
use App\Admin\Settings\Actions\CompleteSetupAction;
use App\Admin\Settings\AiProviderCheck;
use App\Admin\Settings\AiProviderIcon;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\Data\MailSetupData;
use App\Admin\Settings\Data\RerankerSetupData;
use App\Admin\Settings\Data\SetupData;
use App\Admin\Settings\Data\StorageSetupData;
use App\Admin\Settings\FirecrawlCheck;
use App\Admin\Settings\LlmTaskText;
use App\Admin\Settings\MailField;
use App\Admin\Settings\MailProvider;
use App\Admin\Settings\MailSettings;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\RerankerCheck;
use App\Admin\Settings\SetupRequirements;
use App\Admin\Settings\SetupStatus;
use App\Admin\Settings\StorageCheck;
use App\Admin\Settings\StorageSettings;
use App\Admin\Settings\TaskModelPreset;
use App\Admin\Status\Data\ServiceStatusData;
use App\Admin\Status\Service;
use App\Admin\Status\ServiceChecks;
use App\Admin\Status\ServiceState;
use Closure;
use Domain\Knowledge\Embedding\Data\EmbeddingColumnData;
use Domain\Knowledge\Embedding\Enums\EmbeddingCheckStatus;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Knowledge\Embedding\Queries\StoredEmbeddingColumnsQuery;
use Domain\Knowledge\Embedding\Rerank\RerankerFactory;
use Domain\Knowledge\Embedding\Rerank\RerankModelCatalog;
use Domain\Knowledge\Embedding\Services\EmbeddingCheck;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Embedding\Services\OpenRouterEmbeddingModels;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Retrieval\Actions\EnablePgTextsearchAction;
use Domain\Knowledge\Retrieval\Enums\PgTextsearchState;
use Domain\Knowledge\Retrieval\Services\PgTextsearchSupport;
use Domain\Llm\Actions\Catalog\SyncLlmFactsAction;
use Domain\Llm\Data\Catalog\CustomLlmServerData;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\AiCapability;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Enums\ModelListStatus;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\ModelListing;
use Domain\Llm\Services\ModelPresets;
use Domain\Llm\Services\ProviderAdapters;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The setup of a new install: everything the app needs to work, step by
 * step. Each key is checked before the next step and saved once.
 *
 * 1. Server: the services ChatVia.ai runs on (database, Redis, queue,
 *    scheduler, live updates), checked live as on the Status page, and
 *    keyword search: pg_textsearch is recommended, with why and how to get
 *    it, and turned on here when the database has it loaded. Next with a
 *    service down asks first ({@see serverDownAction()}); nothing is saved.
 * 2. Connect AI: a provider and its key (its model list checks it).
 * 3. Models: the chat models agents can use (one or more, one of them the
 *    default for new agents; the provider's recommended ones are picked,
 *    {@see ModelPresets}), the model for quick tasks (guardrails, follow-up
 *    questions, titles…: a small one when the provider has one, else
 *    the chat model), the model for image descriptions (one that reads
 *    images; with none, image descriptions are off), the embedding service
 *    (it starts on the provider's own when that fits; any other may be
 *    picked, with its key) and model (picked from OpenRouter's list there),
 *    checked with a test embedding, and the
 *    reranker: OpenRouter (its live list of rerankers), Cohere, a server
 *    of your own or none, checked with a test question.
 * 4. Documents: Mistral OCR, Azure Document Intelligence or none (the
 *    default). Without a reader, PDF, Word, slides and images cannot be
 *    uploaded.
 * 5. Websites: Firecrawl or none (the default). Without it, websites cannot
 *    be added as knowledge.
 * 6. Speech to text: Scaleway AI, OpenAI or none (the default). Without it,
 *    audio and video files cannot be uploaded and voice messages stay
 *    audio.
 * 7. Voice conversations: OpenAI's Realtime API or none (the default).
 *    It needs an OpenAI key even when agents answer with another provider;
 *    each agent still turns calls on in its own setup.
 * 8. Storage: this server (the default) or S3-compatible storage (checked
 *    with a test file).
 * 9. Mailer: none (the default) or a mail service. Without one nobody
 *    receives sign-up, password or invitation emails.
 * 10. Final: "Complete", the choices made, and where the rest (chunking,
 *    search…), which keeps its default values, is changed.
 *
 * Each step starts on the safe choice ("Not now", "This server") unless
 * it is set up already: what `.env` suggests is not a choice. The one
 * exception is the reranker with OpenRouter as the provider: the same key
 * covers it, so it starts on OpenRouter. "Not now"
 * turns the service off, so "Automatic" does not turn it on behind the
 * admin's back. A service on the account of an earlier step uses the key
 * typed there.
 *
 * Every admin panel page leads here until it is done or skipped
 * ({@see RequireSetup}). Skipping warns with what is still missing
 * ({@see SetupRequirements}); while something is, Settings offers to finish
 * the setup. Once it is all set, everything is changed in Settings and the
 * setup does not open again. Saved keys are never shown.
 *
 * @property-read Schema $form
 */
class Setup extends SimplePage
{
    /** The option of a server of your own, beside the providers. */
    private const string CUSTOM = 'custom';

    /** The option of no document reader, website import or speech to text. */
    private const string NOT_NOW = 'none';

    /**
     * The fields that choose a service, in step order, each with the field of
     * its key and the step it is on. A service whose account an earlier step
     * chose uses the key typed there.
     */
    private const array KEY_FIELDS = [
        'provider' => ['api_key', 'Connect AI'],
        'embedding_provider' => ['embedding_api_key', 'Models'],
        'reranker' => ['rerank_api_key', 'Models'],
        'document_reader' => ['document_api_key', 'Documents'],
        'transcriber' => ['transcription_api_key', 'Speech to text'],
        'voice' => ['voice_api_key', 'Voice conversations'],
    ];

    /** The label of the server step, which Next leaves once a service down is confirmed. */
    private const string SERVER_STEP = 'Server';

    /** The services the server step checks: what runs beside the web server. Storage and email have steps of their own. */
    private const array SERVER_SERVICES = [Service::Database, Service::Redis, Service::Queue, Service::Scheduler, Service::Realtime, Service::ModelDetails];

    /** The label of the documents step, which Next leaves once "Not now" is confirmed. */
    private const string DOCUMENTS_STEP = 'Documents';

    /** The option of website import with Firecrawl. */
    private const string FIRECRAWL = 'firecrawl';

    #[\Override]
    protected Width|string|null $maxWidth = Width::SixExtraLarge;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, string> The models the chosen provider lists: id => label. */
    public array $models = [];

    /** Whether the list has a recommended model that reads images ({@see ModelPresets::visionModel()}). */
    public bool $listsVisionModel = false;

    /** How reading the model list went ({@see ModelListStatus}), or null before it was read. */
    public ?string $listStatus = null;

    /** Why there is no list, or null. */
    public ?string $listMessage = null;

    /** The provider, address and key the list was last read with, so Next does not read it again. */
    public ?string $listedFor = null;

    /** The embedding service, model and key last checked, so Finish does not check them again. */
    public ?string $embeddingCheckedFor = null;

    /** The reranker, model, key and address last checked, so Finish does not check them again. */
    public ?string $rerankerCheckedFor = null;

    /** @var array<string, array<string, mixed>> The server step's checks, by {@see Service} value. */
    public array $server = [];

    /** Whether the admin agreed to go on with a service down, so Next does not warn again. */
    public bool $serverDownConfirmed = false;

    /** Whether the admin agreed to go on without a document reader, so Next does not warn again. */
    public bool $noReaderConfirmed = false;

    /** How ready pg_textsearch is, read once per request. */
    private ?PgTextsearchState $pgTextsearch = null;

    /** The embedding columns as the database has them, read once per request. */
    private ?EmbeddingColumnData $storedColumn = null;

    public function mount(): void
    {
        // Done in full: Settings changes the rest. A skipped setup can be finished.
        if (SetupStatus::current() === SetupStatus::Completed) {
            $this->redirect(Filament::getUrl());

            return;
        }

        $provider = $this->suggestedProvider();
        $embedding = $this->suggestedEmbedding($provider);
        $mailer = MailProvider::tryFrom((string) app(MailSettings::class)->mailer) ?? MailProvider::Log;

        $this->form->fill([
            'provider' => $provider->value,
            'server_name' => 'My server',
            'embedding_provider' => $embedding?->value,
            'embedding_model' => $embedding instanceof EmbeddingProvider ? config($embedding->modelConfig()) : null,
            'chat_models' => [],
            'quick_task_model' => null,
            'vision_model' => null,
            'reranker' => $this->suggestedReranker($provider),
            'openrouter_rerank_model' => config('chatvia.ai.rerank.openrouter_model') ?: RerankModelCatalog::DEFAULT_OPENROUTER,
            'cohere_rerank_model' => config('services.cohere.rerank_model') ?: array_key_first(RerankModelCatalog::COHERE),
            'rerank_custom_url' => config('chatvia.ai.rerank.custom.url'),
            'rerank_custom_model' => config('chatvia.ai.rerank.custom.model'),
            'document_reader' => $this->suggestedReader($provider),
            'website_import' => filled(config('services.firecrawl.key')) ? self::FIRECRAWL : self::NOT_NOW,
            'transcriber' => in_array(app(AiSettings::class)->transcriber, $this->speechOptions(), true) ? app(AiSettings::class)->transcriber : self::NOT_NOW,
            'voice' => in_array(app(AiSettings::class)->voice, $this->voiceOptions(), true) ? app(AiSettings::class)->voice : self::NOT_NOW,
            'storage_driver' => app(StorageSettings::class)->driver === 's3' ? 's3' : 'local',
            'mailer' => $mailer === MailProvider::Sendmail ? MailProvider::Log->value : $mailer->value,
            'from_address' => config('mail.from.address'),
            'from_name' => 'ChatVia.ai',
        ]);
    }

    /** The page shows the large mark instead ({@see AdminPanelProvider}). */
    public function hasLogo(): bool
    {
        return false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Set up ChatVia.ai';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Welcome to ChatVia.ai';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'A few short steps and your agents can answer. You can change all of it later in Settings.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('complete'),
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
                $this->serverStep(),
                $this->connectStep(),
                $this->modelsStep(),
                $this->documentsStep(),
                $this->websitesStep(),
                $this->speechStep(),
                $this->voiceStep(),
                $this->storageStep(),
                $this->emailStep(),
                $this->finalStep(),
            ])
                ->contained(false)
                ->extraAttributes(['class' => 'cv-setup-wizard'])
                // Shown on every step, in the bar with Back and Next.
                ->cancelAction($this->wayOutLink())
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
        $this->checkConnection();
        $this->checkModels();
        $this->checkReranker();
        $this->checkReader();
        $this->checkFirecrawl();
        $this->checkServiceKey('transcriber', 'transcription_api_key');
        $this->checkServiceKey('voice', 'voice_api_key');
        $this->checkStorage();

        $account = $this->account($state);
        $embedding = $this->embeddingProvider($state);
        $reader = AiProvider::tryFrom((string) ($state['document_reader'] ?? ''));
        $transcriber = AiProvider::tryFrom((string) ($state['transcriber'] ?? ''));
        $voice = AiProvider::tryFrom((string) ($state['voice'] ?? ''));

        $model = app(CompleteSetupAction::class)->execute(new SetupData(
            chatKind: $this->chatKind($state),
            apiKey: $this->filled($state, 'api_key'),
            chatModels: $this->chosenModels($state),
            defaultModel: (string) ($state['default_model'] ?? $this->chosenModels($state)[0]->id),
            quickTaskModel: $this->listedChoice($state, 'quick_task_model'),
            visionModel: $this->listedChoice($state, 'vision_model'),
            server: $account instanceof AiProvider ? null : new CustomLlmServerData(
                name: (string) $state['server_name'],
                baseUrl: (string) $state['server_url'],
                apiKey: $this->filled($state, 'api_key'),
            ),
            embeddingProvider: $embedding,
            embeddingModel: $this->filled($state, 'embedding_model'),
            embeddingApiKey: $embedding instanceof EmbeddingProvider && ! $this->reusesEarlierKey('embedding_provider', $state) ? $this->filled($state, 'embedding_api_key') : null,
            reranker: $this->rerankerData($state),
            documentReader: $reader,
            documentApiKey: $reader instanceof AiProvider && ! $this->reusesEarlierKey('document_reader', $state) ? $this->filled($state, 'document_api_key') : null,
            documentEndpoint: $reader === AiProvider::Azure ? $this->filled($state, 'document_endpoint') : null,
            firecrawlApiKey: ($state['website_import'] ?? null) === self::FIRECRAWL ? $this->filled($state, 'firecrawl_api_key') : null,
            transcriber: $transcriber,
            transcriptionApiKey: $transcriber instanceof AiProvider && ! $this->reusesEarlierKey('transcriber', $state) ? $this->filled($state, 'transcription_api_key') : null,
            voice: $voice,
            voiceApiKey: $voice instanceof AiProvider && ! $this->reusesEarlierKey('voice', $state) ? $this->filled($state, 'voice_api_key') : null,
            storage: $this->storageData($state),
            mail: new MailSetupData(
                provider: MailProvider::from((string) $state['mailer']),
                values: collect(MailProvider::from((string) $state['mailer'])->fields())
                    ->mapWithKeys(fn (MailField $field): array => [$field->property => $state[$field->property] ?? null])
                    ->all(),
                fromAddress: $this->filled($state, 'from_address'),
                fromName: $this->filled($state, 'from_name'),
            ),
        ));

        Cache::forget($this->modelCacheKey());

        Notification::make()
            ->success()
            ->title('ChatVia.ai is ready')
            ->body("New agents answer with {$model->name}.")
            ->send();

        $this->redirect(Filament::getUrl());
    }

    // --- Steps ---------------------------------------------------------------

    private function serverStep(): Step
    {
        return Step::make(self::SERVER_STEP)
            ->description('What ChatVia.ai runs on')
            ->icon(Heroicon::OutlinedServerStack)
            ->schema([
                Section::make('Services')
                    ->description('ChatVia.ai needs these running beside the web server. Each one is checked live, as on the Status page.')
                    ->icon(Heroicon::OutlinedServerStack)
                    ->schema([
                        View::make('filament.setup.server')->viewData(fn (): array => [
                            'services' => array_map(fn (Service $service): array => [
                                $service,
                                isset($this->server[$service->value]) ? ServiceStatusData::from($this->server[$service->value]) : null,
                            ], self::SERVER_SERVICES),
                            'checked' => $this->server !== [],
                            'headline' => $this->serverHeadline(),
                        ]),
                    ]),
                Section::make('Keyword search')
                    ->description('Agents find answers in two ways: by meaning, and by the words of the question. Both run in the database.')
                    ->icon(Heroicon::OutlinedMagnifyingGlass)
                    ->schema([$this->keywordSearchCallout()]),
            ])
            ->afterValidation(fn () => $this->confirmServer());
    }

    private function connectStep(): Step
    {
        return Step::make('Connect AI')
            ->description('Your AI provider and its key')
            ->icon(Heroicon::OutlinedKey)
            ->schema([
                Section::make('AI provider')
                    ->description('Choose the main AI provider. Your agents use its models to answer.')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->schema($this->providerFields()),
            ])
            ->afterValidation(fn () => $this->showingErrors($this->checkConnection(...)));
    }

    private function modelsStep(): Step
    {
        return Step::make('Models')
            ->description('What agents answer with')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->schema([
                Callout::make(fn (Get $get): string => 'Connected to '.$this->providerLabel($get('provider')))
                    ->description(fn (Get $get): ?string => ($account = AiProvider::tryFrom((string) $get('provider'))) instanceof AiProvider
                        ? 'This key gives you: '.$this->capabilityList($account).'.'
                        : null)
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->success(),
                Section::make('Chat models')
                    ->description('The models agents can use. Each agent picks one; new agents start with the default. You can add more later in Settings → AI models.')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->schema($this->chatModelFields()),
                Section::make('Embeddings')
                    ->description(fn (Get $get): string => $this->embeddingDescription($get))
                    ->icon(Heroicon::OutlinedCubeTransparent)
                    ->schema([...$this->embeddingServiceFields(), ...$this->embeddingModelFields()]),
                Section::make('Reranker')
                    ->description('Scores what search finds, and your tools, against each question, so agents use only the relevant ones.')
                    ->icon(Heroicon::OutlinedBarsArrowDown)
                    ->schema($this->rerankerFields()),
            ])
            ->afterValidation(fn () => $this->showingErrors(function (): void {
                $this->checkModels();
                $this->checkReranker();
            }));
    }

    private function documentsStep(): Step
    {
        return Step::make(self::DOCUMENTS_STEP)
            ->description('PDFs, Office files and images')
            ->icon(Heroicon::OutlinedDocumentText)
            ->schema([
                Section::make('Document reader')
                    ->description('Reads PDF, Word, slides and images, with their tables, so agents can learn from them.')
                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                    ->schema([
                        Radio::make('document_reader')
                            ->hiddenLabel()
                            ->options([
                                AiProvider::Mistral->value => 'Mistral OCR',
                                AiProvider::Azure->value => 'Azure Document Intelligence',
                                self::NOT_NOW => 'Not now',
                            ])
                            ->descriptions([
                                AiProvider::Mistral->value => 'Reads scans, images and tables, also the pictures inside Word and PowerPoint files. Each file is sent to Mistral directly.',
                                AiProvider::Azure->value => 'Reads scans, images and tables; skips the pictures inside Word and PowerPoint files. Each file is sent to Azure directly. Use the standard tier (S0): the free one reads only 2 pages of each file.',
                                self::NOT_NOW => 'People cannot upload PDF, Word, slides or images. Text, Markdown, spreadsheets (Excel, CSV) and ebooks still work: the app reads them itself.',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (): bool => $this->noReaderConfirmed = false),
                        $this->keyCallout('document_reader'),
                        $this->serviceKeyField('document_api_key', 'document_reader', 'getReaderKey'),
                        TextInput::make('document_endpoint')
                            ->label('Endpoint')
                            ->url()
                            ->placeholder('https://my-resource.cognitiveservices.azure.com')
                            ->helperText('Your resource\'s endpoint, from the Azure portal.')
                            ->required(fn (Get $get): bool => blank(config('services.azure_document_intelligence.endpoint')))
                            ->visible(fn (Get $get): bool => $get('document_reader') === AiProvider::Azure->value && ! $this->usesSavedKey('document_reader', $get)),
                    ]),
            ])
            ->afterValidation(fn () => $this->showingErrors(function (): void {
                $this->checkReader();
                $this->confirmNoReader();
            }));
    }

    private function websitesStep(): Step
    {
        return Step::make('Websites')
            ->description('Importing websites')
            ->icon(Heroicon::OutlinedGlobeAlt)
            ->schema([
                Section::make('Website import')
                    ->description('To learn from a website, agents need Firecrawl. It finds the pages of the site and reads each one, also pages built with JavaScript.')
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->schema([
                        Radio::make('website_import')
                            ->hiddenLabel()
                            ->options([self::FIRECRAWL => 'Firecrawl', self::NOT_NOW => 'Not now'])
                            ->descriptions([
                                self::FIRECRAWL => 'Pay per page read. You can also run your own Firecrawl server (Settings → Knowledge).',
                                self::NOT_NOW => 'People cannot add websites as knowledge. Files, texts and Q&A still work.',
                            ])
                            ->required()
                            ->live(),
                        TextInput::make('firecrawl_api_key')
                            ->label('Firecrawl API key')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->required(fn (Get $get): bool => $get('website_import') === self::FIRECRAWL && blank(config('services.firecrawl.key')))
                            ->placeholder(fn (): ?string => filled(config('services.firecrawl.key')) ? '•••••••• saved, leave blank to keep' : null)
                            ->helperText('Stored encrypted. It is never shown again.')
                            ->hintAction(Action::make('getFirecrawlKey')
                                ->label('Get a key')
                                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                                ->iconPosition('after')
                                ->url('https://www.firecrawl.dev/app/api-keys', shouldOpenInNewTab: true))
                            ->visible(fn (Get $get): bool => $get('website_import') === self::FIRECRAWL),
                    ]),
            ])
            ->afterValidation(fn () => $this->showingErrors($this->checkFirecrawl(...)));
    }

    private function speechStep(): Step
    {
        $services = array_map(AiProvider::from(...), $this->speechOptions());

        return Step::make('Speech to text')
            ->description('Audio and video files')
            ->icon(Heroicon::OutlinedMicrophone)
            ->schema([
                Section::make('Speech to text')
                    ->description('Turns speech into text. Agents can then learn from audio and video files and search what is said in them, and voice messages become text.')
                    ->icon(Heroicon::OutlinedMicrophone)
                    ->schema([
                        Radio::make('transcriber')
                            ->hiddenLabel()
                            ->options([
                                ...collect($services)->mapWithKeys(fn (AiProvider $service): array => [$service->value => $service->label()])->all(),
                                self::NOT_NOW => 'Not now',
                            ])
                            ->descriptions([
                                ...collect($services)->mapWithKeys(fn (AiProvider $service): array => [$service->value => "Whisper on {$service->label()}."])->all(),
                                self::NOT_NOW => 'People cannot upload audio or video files, and voice messages are not turned into text.',
                            ])
                            ->required()
                            ->live(),
                        $this->keyCallout('transcriber'),
                        $this->serviceKeyField('transcription_api_key', 'transcriber', 'getSpeechKey'),
                    ]),
            ])
            ->afterValidation(fn () => $this->showingErrors(fn () => $this->checkServiceKey('transcriber', 'transcription_api_key')));
    }

    private function voiceStep(): Step
    {
        $services = array_map(AiProvider::from(...), $this->voiceOptions());

        return Step::make('Voice conversations')
            ->description('Live calls with agents')
            ->icon(Heroicon::OutlinedPhone)
            ->schema([
                Section::make('Voice conversations')
                    ->description('Visitors can talk with an agent in a live call, in the hosted chat and the chat widget. The agent listens, answers out loud and uses its tools. This uses the OpenAI Realtime API, so it needs an OpenAI key, also when your agents answer with another provider.')
                    ->icon(Heroicon::OutlinedPhone)
                    ->schema([
                        Radio::make('voice')
                            ->hiddenLabel()
                            ->options([
                                ...collect($services)->mapWithKeys(fn (AiProvider $service): array => [$service->value => "{$service->label()} Realtime"])->all(),
                                self::NOT_NOW => 'Not now',
                            ])
                            ->descriptions([
                                ...collect($services)->mapWithKeys(fn (AiProvider $service): array => [$service->value => 'Each agent still turns calls on in its own setup. A call costs much more than a typed chat.'])->all(),
                                self::NOT_NOW => 'Visitors chat by typing. The call button is hidden. You can turn calls on later in Settings → AI models.',
                            ])
                            ->required()
                            ->live(),
                        $this->keyCallout('voice'),
                        $this->serviceKeyField('voice_api_key', 'voice', 'getVoiceKey'),
                    ]),
            ])
            ->afterValidation(fn () => $this->showingErrors(fn () => $this->checkServiceKey('voice', 'voice_api_key')));
    }

    private function storageStep(): Step
    {
        return Step::make('Storage')
            ->description('Where files are kept')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->schema([
                Section::make('Where files are kept')
                    ->description('Knowledge files, chat attachments and backups.')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->schema([
                        Radio::make('storage_driver')
                            ->hiddenLabel()
                            ->options(['local' => 'This server', 's3' => 'S3-compatible storage'])
                            ->descriptions([
                                'local' => 'No account needed. Fine for one server.',
                                's3' => 'AWS S3, Scaleway Object Storage, Cloudflare R2, MinIO and others. Keeps files off the server.',
                            ])
                            ->required()
                            ->live(),
                        TextInput::make('s3_endpoint')
                            ->label('Endpoint')
                            ->url()
                            ->helperText('For example https://s3.fr-par.scw.cloud or http://minio:9000. Leave empty for AWS S3.')
                            ->visible(fn (Get $get): bool => $get('storage_driver') === 's3'),
                        TextInput::make('s3_region')
                            ->label('Region')
                            ->helperText('For example fr-par, eu-west-1, or auto for Cloudflare R2.')
                            ->required(fn (Get $get): bool => $get('storage_driver') === 's3')
                            ->visible(fn (Get $get): bool => $get('storage_driver') === 's3'),
                        TextInput::make('s3_key')
                            ->label('Access key ID')
                            ->required(fn (Get $get): bool => $get('storage_driver') === 's3')
                            ->visible(fn (Get $get): bool => $get('storage_driver') === 's3'),
                        TextInput::make('s3_secret')
                            ->label('Secret access key')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->required(fn (Get $get): bool => $get('storage_driver') === 's3' && blank(config('filesystems.disks.s3.secret')))
                            ->visible(fn (Get $get): bool => $get('storage_driver') === 's3'),
                        TextInput::make('s3_bucket')
                            ->label('Bucket')
                            ->helperText('Every file of the app goes here. Keep this bucket private.')
                            ->required(fn (Get $get): bool => $get('storage_driver') === 's3')
                            ->visible(fn (Get $get): bool => $get('storage_driver') === 's3'),
                        Toggle::make('s3_path_style')
                            ->label('Use path-style addresses')
                            ->helperText('MinIO and some other servers need it.')
                            ->visible(fn (Get $get): bool => $get('storage_driver') === 's3'),
                    ]),
            ])
            ->afterValidation(fn () => $this->showingErrors($this->checkStorage(...)));
    }

    private function emailStep(): Step
    {
        return Step::make('Mailer')
            ->description('Sending email')
            ->icon(Heroicon::OutlinedEnvelope)
            ->schema([
                Section::make('Sending email')
                    ->description('Sign-up verification, password resets and invitations.')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->schema([
                        Select::make('mailer')
                            ->label('Send email with')
                            ->options(collect(MailProvider::cases())
                                ->reject(fn (MailProvider $provider): bool => $provider === MailProvider::Sendmail)
                                ->mapWithKeys(fn (MailProvider $provider): array => [$provider->value => $provider === MailProvider::Log ? 'Not now' : $provider->label()])
                                ->all())
                            ->native(false)
                            ->selectablePlaceholder(false)
                            ->helperText(fn (Get $get): ?string => $get('mailer') === MailProvider::Log->value ? null : MailProvider::tryFrom((string) $get('mailer'))?->description())
                            ->required()
                            ->live(),
                        Callout::make('Without email, nobody receives emails')
                            ->description('People cannot verify a sign-up, reset a password, or accept an invitation. You can set up email later in Settings → Email.')
                            ->icon(Heroicon::OutlinedExclamationTriangle)
                            ->warning()
                            ->visible(fn (Get $get): bool => $get('mailer') === MailProvider::Log->value),
                        ...array_merge(...array_map(
                            fn (MailProvider $provider): array => array_map(fn (MailField $field): Field => $this->mailField($provider, $field), $provider->fields()),
                            MailProvider::cases(),
                        )),
                        TextInput::make('from_address')
                            ->label('Sender address')
                            ->email()
                            ->required(fn (Get $get): bool => $get('mailer') !== MailProvider::Log->value)
                            ->visible(fn (Get $get): bool => $get('mailer') !== MailProvider::Log->value),
                        TextInput::make('from_name')
                            ->label('Sender name')
                            ->visible(fn (Get $get): bool => $get('mailer') !== MailProvider::Log->value),
                    ]),
            ]);
    }

    private function finalStep(): Step
    {
        return Step::make('Final')
            ->description('Check and finish')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->schema([
                View::make('filament.setup.complete'),
                Section::make('Your choices')
                    ->description('What Finish setup saves.')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->schema([
                        View::make('filament.setup.summary')
                            ->viewData(fn (Get $get): array => ['rows' => $this->choices($get)]),
                    ]),
                Section::make('Default values')
                    ->description('Everything else starts with default values that suit most installs. You can change them at any time in Settings.')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->schema([
                        View::make('filament.setup.summary')
                            ->viewData(['rows' => [
                                'Settings → Knowledge' => 'Search, the AI enhancement of sources, follow-up questions, and the file types people can upload.',
                                'Settings → Splitting' => 'How texts are split into chunks.',
                                'Settings → AI models' => 'More chat models, and a model for each app task.',
                                'Settings → Branding' => 'The "Powered by ChatVia.ai" credit under hosted chats and widgets.',
                                'Settings → Workspaces' => 'Who can sign up, and how long conversations are kept.',
                                'Settings → Sign-in' => 'Sign-in with other accounts.',
                                'Settings → Security' => 'The bot check on public forms, guardrails, tool call limits and the security log.',
                            ]]),
                    ]),
            ]);
    }

    /**
     * What each step chose, in plain words, for the last step.
     *
     * @return array<string, string>
     */
    private function choices(Get $get): array
    {
        $chosen = array_values(array_filter((array) $get('chat_models'), is_string(...)));
        $models = $chosen === []
            ? (string) $get('chat_model_id')
            : implode(', ', array_map(
                fn (string $id): string => ($this->models[$id] ?? $id).($id === $get('default_model') && count($chosen) > 1 ? ' (default)' : ''),
                $chosen,
            ));
        $embedding = $this->embeddingProvider($get);
        $mailer = MailProvider::tryFrom((string) $get('mailer'));

        return [
            'AI provider' => $get('provider') === self::CUSTOM ? (string) $get('server_name') : $this->providerLabel($get('provider')),
            'Chat models' => $models,
            'Model for quick tasks' => filled($get('quick_task_model')) && $this->models !== []
                ? ($this->models[$get('quick_task_model')] ?? (string) $get('quick_task_model'))
                : 'Same as the chat model',
            'Image descriptions' => filled($get('vision_model')) && $this->models !== []
                ? ($this->models[$get('vision_model')] ?? (string) $get('vision_model'))
                : 'Off',
            'Embeddings' => $embedding instanceof EmbeddingProvider
                ? $embedding->label().(filled($get('embedding_model')) ? " · {$get('embedding_model')}" : '')
                : 'Already set up',
            'Reranker' => $this->rerankerSummary(),
            'Documents' => AiProvider::tryFrom((string) $get('document_reader'))?->label() ?? 'Not now',
            'Websites' => $get('website_import') === self::FIRECRAWL ? 'Firecrawl' : 'Not now',
            'Speech to text' => AiProvider::tryFrom((string) $get('transcriber'))?->label() ?? 'Not now',
            'Voice conversations' => ($voice = AiProvider::tryFrom((string) $get('voice'))) instanceof AiProvider ? "{$voice->label()} Realtime" : 'Not now',
            'Storage' => $get('storage_driver') === 's3' ? 'S3-compatible storage' : 'This server',
            'Mailer' => ! $mailer instanceof MailProvider || $mailer === MailProvider::Log ? 'Not now' : $mailer->label(),
        ];
    }

    /**
     * @return list<Component>
     */
    private function providerFields(): array
    {
        return [
            CardRadio::make('provider')
                ->hiddenLabel()
                ->options($this->providerOptions())
                ->descriptions($this->providerDescriptions())
                ->icons($this->providerIcons())
                ->tags($this->providerTags())
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    $this->forgetList();
                    $embedding = $this->suggestedEmbedding(AiProvider::tryFrom((string) $state));
                    $set('embedding_provider', $embedding?->value);
                    $set('embedding_model', $embedding instanceof EmbeddingProvider ? config($embedding->modelConfig()) : null);
                    $set('document_reader', $this->suggestedReader(AiProvider::tryFrom((string) $state)));
                    $set('reranker', $this->suggestedReranker(AiProvider::tryFrom((string) $state)));
                }),
            TextInput::make('server_name')
                ->label('Name')
                ->helperText('How the server is listed, such as "Ollama on the office server".')
                ->required()
                ->maxLength(100)
                ->visible(fn (Get $get): bool => $get('provider') === self::CUSTOM),
            TextInput::make('server_url')
                ->label('Address')
                ->url()
                ->placeholder('http://localhost:11434/v1')
                ->helperText('The address of its OpenAI-compatible API, usually ending in /v1.')
                ->required()
                ->visible(fn (Get $get): bool => $get('provider') === self::CUSTOM)
                ->live(onBlur: true)
                ->afterStateUpdated(fn () => $this->forgetList()),
            TextInput::make('api_key')
                ->label(fn (Get $get): string => ($account = AiProvider::tryFrom((string) $get('provider'))) instanceof AiProvider ? "{$account->label()} API key" : 'API key (optional)')
                ->password()
                ->revealable()
                ->autocomplete('new-password')
                ->required(fn (Get $get): bool => ($account = AiProvider::tryFrom((string) $get('provider'))) instanceof AiProvider && ! $account->isConnected())
                ->placeholder(fn (Get $get): ?string => AiProvider::tryFrom((string) $get('provider'))?->isConnected() ? '•••••••• saved, leave blank to keep' : null)
                ->helperText(fn (Get $get): string => $get('provider') === self::CUSTOM
                    ? 'Leave empty when your server has no key.'
                    : 'Stored encrypted. It is never shown again.')
                ->hintAction(fn (Get $get): ?Action => ($url = AiProvider::tryFrom((string) $get('provider'))?->keysUrl()) === null ? null : Action::make('getKey')
                    ->label('Get a key')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->iconPosition('after')
                    ->url($url, shouldOpenInNewTab: true))
                ->live(onBlur: true)
                ->afterStateUpdated(fn () => $this->forgetList()),
        ];
    }

    /**
     * The embedding service, as cards like the reranker's: every service
     * that returns the stored vector size. It starts on the AI provider's
     * own when that fits, and any other may be picked, with its key (asked
     * only when no earlier step has it and none is saved). Once sources are
     * trained it stays as it is ({@see embeddingModelFields()}).
     *
     * @return list<Component>
     */
    private function embeddingServiceFields(): array
    {
        $fitting = EmbeddingProvider::fitting();

        return [
            CardRadio::make('embedding_provider')
                ->hiddenLabel()
                ->options(collect($fitting)->mapWithKeys(fn (EmbeddingProvider $service): array => [$service->value => $service->label()])->all())
                ->descriptions(collect($fitting)->mapWithKeys(fn (EmbeddingProvider $service): array => [$service->value => 'Model: '.config($service->modelConfig()).'.'])->all())
                ->icons(collect($fitting)->mapWithKeys(fn (EmbeddingProvider $service): array => [$service->value => AiProviderIcon::of($service->account())])->all())
                ->in(array_map(fn (EmbeddingProvider $service): string => $service->value, $fitting))
                ->required(fn (): bool => ! $this->trained())
                ->visible(fn (): bool => ! $this->trained())
                ->live()
                ->afterStateUpdated(fn (Set $set, ?string $state): mixed => $set('embedding_model', ($service = EmbeddingProvider::tryFrom((string) $state)) instanceof EmbeddingProvider ? config($service->modelConfig()) : null)),
            $this->keyCallout('embedding_provider'),
            $this->serviceKeyField('embedding_api_key', 'embedding_provider', 'getEmbeddingKey'),
        ];
    }

    /**
     * The embedding model: picked from OpenRouter's list, typed for the
     * other services. Once sources are trained it stays as it is.
     *
     * @return list<Component>
     */
    private function embeddingModelFields(): array
    {
        if ($this->trained()) {
            $provider = EmbeddingProvider::configured();
            $model = $provider instanceof EmbeddingProvider ? (string) config($provider->modelConfig()) : null;

            return [
                Callout::make('Embeddings are already set up')
                    ->description(sprintf(
                        'Sources are trained with %s%s, %d dimensions, so it stays as it is.',
                        $provider?->label() ?? 'the service in use',
                        filled($model) ? " ({$model})" : '',
                        $this->storedDimensions(),
                    ))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->success(),
            ];
        }

        $help = "It must return {$this->storedDimensions()} dimensions. Next checks it with a test request.";

        return [
            Select::make('embedding_model')
                ->label('Embedding model')
                ->options(fn (): array => app(OpenRouterEmbeddingModels::class)->options($this->storedDimensions()))
                ->searchable()
                ->required()
                ->helperText($help)
                ->visible(fn (Get $get): bool => $this->embeddingProvider($get) === EmbeddingProvider::OpenRouter),
            TextInput::make('embedding_model')
                ->label(fn (Get $get): string => ($this->embeddingProvider($get)?->label() ?? 'Embedding').' model')
                ->placeholder(fn (Get $get): ?string => ($provider = $this->embeddingProvider($get)) instanceof EmbeddingProvider ? (string) config($provider->modelConfig()) : null)
                ->helperText("The model's name exactly as the service writes it. The suggested one fits. {$help}")
                ->maxLength(255)
                ->visible(fn (Get $get): bool => $this->embeddingProvider($get) !== EmbeddingProvider::OpenRouter),
        ];
    }

    /**
     * The reranker: OpenRouter with a model from its live list, Cohere, a
     * server of your own, or none. OpenRouter and Cohere use the key of an
     * earlier step on the same account, else the saved one, else one typed
     * here.
     *
     * @return list<Component>
     */
    private function rerankerFields(): array
    {
        $chosen = fn (string $driver): Closure => fn (Get $get): bool => $get('reranker') === $driver;

        return [
            CardRadio::make('reranker')
                ->hiddenLabel()
                ->options([
                    AiProvider::Cohere->value => 'Cohere',
                    AiProvider::OpenRouter->value => 'OpenRouter',
                    self::CUSTOM => 'Your own server',
                    self::NOT_NOW => 'Not now',
                ])
                ->descriptions([
                    AiProvider::Cohere->value => 'Cohere Rerank 4, on Cohere\'s own API.',
                    AiProvider::OpenRouter->value => 'The rerankers of Cohere, Voyage and Qwen, with one key.',
                    self::CUSTOM => 'vLLM, Infinity or another server with the common /rerank API.',
                    self::NOT_NOW => 'Search uses a basic similarity check instead, and finds the right part less often.',
                ])
                ->icons([
                    AiProvider::Cohere->value => AiProviderIcon::of(AiProvider::Cohere),
                    AiProvider::OpenRouter->value => AiProviderIcon::of(AiProvider::OpenRouter),
                    self::CUSTOM => Heroicon::OutlinedServerStack,
                    self::NOT_NOW => Heroicon::OutlinedNoSymbol,
                ])
                ->required()
                ->live(),
            $this->keyCallout('reranker'),
            $this->serviceKeyField('rerank_api_key', 'reranker', 'getRerankKey'),
            Select::make('openrouter_rerank_model')
                ->label('Model')
                ->options(fn (): array => app(RerankModelCatalog::class)->openRouterOptions())
                ->searchable()
                ->required($chosen(AiProvider::OpenRouter->value))
                ->helperText('The strongest first. The list comes from OpenRouter, so new rerankers show up here.')
                ->visible($chosen(AiProvider::OpenRouter->value)),
            Select::make('cohere_rerank_model')
                ->label('Model')
                ->options(fn (): array => app(RerankModelCatalog::class)->cohereOptions())
                ->native(false)
                ->selectablePlaceholder(false)
                ->required($chosen(AiProvider::Cohere->value))
                ->visible($chosen(AiProvider::Cohere->value)),
            TextInput::make('rerank_custom_url')
                ->label('Address')
                ->url()
                ->placeholder('http://reranker:8000/v1')
                ->helperText('The address of its /rerank API.')
                ->required($chosen(self::CUSTOM))
                ->visible($chosen(self::CUSTOM)),
            TextInput::make('rerank_custom_model')
                ->label('Model')
                ->placeholder('Qwen/Qwen3-Reranker-4B')
                ->helperText('The model\'s name exactly as your server writes it.')
                ->required($chosen(self::CUSTOM))
                ->maxLength(255)
                ->visible($chosen(self::CUSTOM)),
            TextInput::make('rerank_custom_key')
                ->label('API key (optional)')
                ->password()
                ->revealable()
                ->autocomplete('new-password')
                ->placeholder(fn (): ?string => filled(config('chatvia.ai.rerank.custom.key')) ? '•••••••• saved, leave blank to keep' : null)
                ->helperText('Only when your server asks for one. Stored encrypted.')
                ->visible($chosen(self::CUSTOM)),
        ];
    }

    /** The reranker chosen, in plain words, for the last step. */
    private function rerankerSummary(): string
    {
        $reranker = $this->rerankerData($this->form->getRawState());

        return match ($reranker->driver) {
            AiProvider::OpenRouter->value => "OpenRouter · {$reranker->model}",
            AiProvider::Cohere->value => "Cohere · {$reranker->model}",
            self::CUSTOM => "Your own server · {$reranker->model}",
            default => 'Not now',
        };
    }

    /**
     * What the embeddings do, that their service can differ from the AI
     * provider, and why it must when the provider makes none of the stored
     * size.
     */
    private function embeddingDescription(Get $get): string
    {
        $does = 'Embeddings turn your documents, and each question, into vectors, so agents find the right part.';

        if ($this->trained()) {
            return $does;
        }

        $account = AiProvider::tryFrom((string) $get('provider'));

        return match (true) {
            ! $account instanceof AiProvider => "{$does} Your own server does not make them here, so pick a service that does.",
            ! $account->can(AiCapability::Embeddings) => "{$does} {$account->label()} does not make them, so pick a service that does.",
            ! EmbeddingProvider::fittingFor($account) instanceof EmbeddingProvider => "{$does} {$account->label()} cannot make the {$this->storedDimensions()}-dimension vectors your database stores, so pick a service that does.",
            default => "{$does} {$account->label()} makes them with the same key, or pick another service.",
        };
    }

    /**
     * @return list<Component>
     */
    private function chatModelFields(): array
    {
        return [
            Callout::make(fn (): ?string => $this->listMessage)
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->warning()
                ->visible(fn (): bool => filled($this->listMessage)),
            Select::make('chat_models')
                ->label('Models agents can use')
                ->multiple()
                ->options(fn (): array => $this->models)
                ->in(fn (): array => array_keys($this->models))
                ->searchable()
                ->required(fn (): bool => $this->models !== [])
                ->minItems(1)
                ->helperText(fn (): string => count($this->models).' chat models. The suggested ones call tools well.')
                ->visible(fn (): bool => $this->models !== [])
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set, ?array $state): void {
                    if (! in_array($get('default_model'), $state ?? [], true)) {
                        $set('default_model', $state[0] ?? null);
                    }
                }),
            Select::make('default_model')
                ->label('Default for new agents')
                ->options(fn (Get $get): array => array_intersect_key($this->models, array_flip((array) $get('chat_models'))))
                ->native(false)
                ->selectablePlaceholder(false)
                ->required(fn (): bool => $this->models !== [])
                ->visible(fn (Get $get): bool => count((array) $get('chat_models')) > 1),
            Select::make('quick_task_model')
                ->label('Model for quick tasks')
                ->options(fn (): array => $this->models)
                ->in(fn (): array => array_keys($this->models))
                ->searchable()
                ->placeholder('Same as the chat model')
                ->helperText(fn (): Htmlable => $this->quickTasksHelp())
                ->visible(fn (): bool => $this->models !== []),
            Select::make('vision_model')
                ->label('Model for image descriptions')
                ->options(fn (): array => $this->models)
                ->in(fn (): array => array_keys($this->models))
                ->searchable()
                ->placeholder('Off')
                ->live()
                ->helperText(fn (Get $get): string => $this->visionHelp($get))
                ->visible(fn (): bool => $this->models !== []),
            Callout::make('Image descriptions are off')
                ->description('Pictures inside PDF files and other documents are not described, so search cannot find what they show. The text of your documents is still read. Add a model that reads images later in Settings → AI models, then turn on Settings → Knowledge → AI enhancement → Describe images.')
                ->icon(Heroicon::OutlinedPhoto)
                ->info()
                ->visible(fn (): bool => $this->models === []),
            TextInput::make('chat_model_id')
                ->label('Model')
                ->placeholder('For example llama3.1:8b')
                ->helperText('The model\'s name exactly as your provider writes it. You can add more later in Settings → AI models.')
                ->required(fn (): bool => $this->models === [])
                ->maxLength(255)
                ->visible(fn (): bool => $this->models === []),
        ];
    }

    /**
     * One setting of an email provider, shown and required only while that
     * provider is picked.
     */
    private function mailField(MailProvider $provider, MailField $field): Field
    {
        $component = match ($field->kind) {
            'secret' => TextInput::make($field->property)->password()->revealable()->autocomplete('new-password'),
            'integer' => TextInput::make($field->property)->integer()->minValue(1)->maxValue(65535),
            'choice' => Select::make($field->property)->options($field->options)->native(false),
            default => TextInput::make($field->property),
        };

        if ($component instanceof TextInput && $field->placeholder !== null) {
            $component->placeholder($field->placeholder);
        }

        if ($field->kind === 'secret' && ($url = $provider->keysUrl()) !== null && $component instanceof TextInput) {
            $component->hintAction(Action::make('getMailKey_'.$provider->value)
                ->label('Get a key')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->iconPosition('after')
                ->url($url, shouldOpenInNewTab: true));
        }

        return $component
            ->label($field->label)
            ->helperText($field->help)
            ->visible(fn (Get $get): bool => $get('mailer') === $provider->value)
            ->required(fn (Get $get): bool => $field->required && $get('mailer') === $provider->value);
    }

    /**
     * The way out, in the action bar: skip the setup (with its warning), or,
     * when a skipped setup was opened again from Settings, back to the panel.
     */
    /** One line over the server cards: checking, all running, or how many need work. */
    private function serverHeadline(): string
    {
        if ($this->server === []) {
            return 'Checking each service…';
        }

        $states = array_map(fn (array $result): ServiceState => ServiceStatusData::from($result)->state, $this->server);
        $down = count(array_filter($states, fn (ServiceState $state): bool => $state === ServiceState::Down));
        $warnings = count(array_filter($states, fn (ServiceState $state): bool => $state === ServiceState::Warning));

        return match (true) {
            $down === 1 => 'One service is down. Fix it, then check again.',
            $down > 1 => "{$down} services are down. Fix them, then check again.",
            $warnings === 1 => 'Everything answers. One service needs a look.',
            $warnings > 1 => "Everything answers. {$warnings} services need a look.",
            default => 'Everything runs.',
        };
    }

    /**
     * What keyword search runs on, and what to do to get pg_textsearch:
     * install it, load it when PostgreSQL starts, or turn it on here.
     */
    private function keywordSearchCallout(): Callout
    {
        $why = 'pg_textsearch ranks the words with BM25, the method search engines use: rare words such as product names, codes and error messages count more than common ones, and a long document does not win just for being long. Without it, ChatVia.ai uses the built-in search of PostgreSQL. That works, but ranks less well, most of all without a reranker.';
        $load = "Add it to <code>shared_preload_libraries</code> in <code>postgresql.conf</code> (<code>shared_preload_libraries = 'pg_textsearch'</code>), restart PostgreSQL, then press <strong>Check again</strong>.";

        return Callout::make(fn (): string => match ($this->pgTextsearchState()) {
            PgTextsearchState::Active => 'pg_textsearch is on',
            PgTextsearchState::SwitchedOff => 'pg_textsearch is installed, but not used',
            PgTextsearchState::ReadyToEnable => 'Turn on pg_textsearch',
            PgTextsearchState::NotPreloaded => 'Load pg_textsearch when PostgreSQL starts',
            PgTextsearchState::NotInstalled => 'Install pg_textsearch (recommended)',
        })
            ->description(fn (): HtmlString => new HtmlString(match ($this->pgTextsearchState()) {
                PgTextsearchState::Active => 'Keyword search ranks with BM25. Nothing to do here.',
                PgTextsearchState::SwitchedOff => 'The <code>SEARCH_LEXICAL_DRIVER</code> setting in <code>.env</code> keeps the built-in search. Remove it, then run <code>php artisan chatvia:search:lexical-indexes</code>. '.e($why),
                PgTextsearchState::ReadyToEnable => e($why).' The database has it loaded: turn it on here. This also rebuilds the search indexes.',
                PgTextsearchState::NotPreloaded => e($why).' It is installed on the database server, but PostgreSQL does not load it. '.$load,
                PgTextsearchState::NotInstalled => e($why).' Install it from its <a href="https://github.com/timescale/pg_textsearch/releases" target="_blank" rel="noopener" class="underline">releases page</a> (the Docker setup and <code>install.sh</code> include it). '.$load.' A managed cloud database often cannot load it: then go on, the built-in search works.',
            }))
            ->icon(fn (): Heroicon => $this->pgTextsearchState() === PgTextsearchState::Active ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedLightBulb)
            ->status(fn (): string => match ($this->pgTextsearchState()) {
                PgTextsearchState::Active => 'success',
                PgTextsearchState::SwitchedOff => 'info',
                default => 'warning',
            })
            ->actions([
                Action::make('enablePgTextsearch')
                    ->label('Turn on pg_textsearch')
                    ->icon(Heroicon::OutlinedBolt)
                    ->visible(fn (): bool => $this->pgTextsearchState() === PgTextsearchState::ReadyToEnable)
                    ->action(fn () => $this->enablePgTextsearch()),
            ]);
    }

    private function pgTextsearchState(): PgTextsearchState
    {
        return $this->pgTextsearch ??= app(PgTextsearchSupport::class)->state();
    }

    private function wayOutLink(): Htmlable
    {
        return new HtmlString(Blade::render(<<<'BLADE'
            @if ($skipped)
                <x-filament::link :href="$url" color="gray" size="sm" icon="heroicon-m-arrow-left">Back to the admin panel</x-filament::link>
            @else
                <x-filament::link tag="button" type="button" color="gray" size="sm" wire:click="mountAction('skip')">Skip the setup for now</x-filament::link>
            @endif
            BLADE, ['skipped' => SetupStatus::current() === SetupStatus::Skipped, 'url' => Filament::getUrl()]));
    }

    /**
     * The quick tasks' help under their model: each named task, and "other
     * small jobs", opens {@see quickTasksAction()} to say what it is.
     */
    private function quickTasksHelp(): Htmlable
    {
        return new HtmlString(Blade::render(<<<'BLADE'
            <x-filament::link tag="button" type="button" size="sm" wire:click="mountAction('quickTasks', { task: 'guardrails' })">Guardrails</x-filament::link>,
            <x-filament::link tag="button" type="button" size="sm" wire:click="mountAction('quickTasks', { task: 'follow_up' })">follow-up questions</x-filament::link>,
            <x-filament::link tag="button" type="button" size="sm" wire:click="mountAction('quickTasks', { task: 'titles' })">titles</x-filament::link>
            and <x-filament::link tag="button" type="button" size="sm" wire:click="mountAction('quickTasks')">other small jobs</x-filament::link>
            run around each reply. A small, fast model keeps replies quick and cheap.
            BLADE));
    }

    /**
     * Says what a quick task is: one task when its name was clicked, else
     * every job the model for quick tasks does, by when it runs.
     */
    public function quickTasksAction(): Action
    {
        $task = fn (array $arguments): ?LlmTask => ($task = LlmTask::tryFrom((string) ($arguments['task'] ?? ''))) instanceof LlmTask && TaskModelPreset::isQuickTask($task) ? $task : null;

        return Action::make('quickTasks')
            ->modalHeading(fn (array $arguments): string => ($picked = $task($arguments)) instanceof LlmTask ? LlmTaskText::label($picked) : 'Quick tasks')
            ->modalDescription(fn (array $arguments): string => ($picked = $task($arguments)) instanceof LlmTask
                ? LlmTaskText::help($picked).' The model for quick tasks does it.'
                : 'Small AI jobs the app does on its own: around each reply, while you set up agents and while sources are trained. The model for quick tasks does all of them. Later, in Settings → AI models, you can give any of them a model of its own.')
            ->modalContent(fn (array $arguments): ?ViewContract => $task($arguments) instanceof LlmTask ? null : view('filament.setup.quick-tasks', [
                'groups' => array_map(
                    fn (array $tasks): array => collect($tasks)->mapWithKeys(fn (LlmTask $task): array => [LlmTaskText::label($task) => LlmTaskText::help($task)])->all(),
                    LlmTaskText::quickTaskGroups(),
                ),
            ]))
            ->modalWidth(fn (array $arguments): Width => $task($arguments) instanceof LlmTask ? Width::Medium : Width::TwoExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    /**
     * Asked when Next leaves the documents step on "Not now": without a
     * reader, PDF, Word, slides and images cannot be uploaded. Going on
     * moves the wizard to the next step; going back keeps the choice open.
     */
    public function noReaderAction(): Action
    {
        return Action::make('noReader')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('warning')
            ->modalHeading('Continue without a document reader?')
            ->modalDescription('Without one, PDF, Word, slides and images cannot be read, so people cannot upload them as knowledge. Text, Markdown, spreadsheets (Excel, CSV) and ebooks still work: the app reads them itself. You can add a reader later in Settings.')
            ->modalSubmitActionLabel('Continue without one')
            ->modalCancelActionLabel('Choose a reader')
            ->color('warning')
            ->action(function (): void {
                $this->noReaderConfirmed = true;
                $this->leaveStep(self::DOCUMENTS_STEP);
            });
    }

    /**
     * Asked when Next leaves the server step with a service down: ChatVia.ai
     * does not work fully without it, but it can be fixed later. Going on
     * moves the wizard to the next step.
     */
    public function serverDownAction(): Action
    {
        return Action::make('serverDown')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('danger')
            ->modalHeading('Continue with a service down?')
            ->modalDescription(fn (): string => sprintf(
                'Down: %s. ChatVia.ai does not work fully while a service is down: agents may not answer, and sources are not trained. You can fix it later: the Status page checks every service at any time.',
                implode(', ', array_map(fn (Service $service): string => $service->label(), $this->servicesDown())),
            ))
            ->modalSubmitActionLabel('Continue anyway')
            ->modalCancelActionLabel('Fix it first')
            ->color('danger')
            ->action(function (): void {
                $this->serverDownConfirmed = true;
                $this->leaveStep(self::SERVER_STEP);
            });
    }

    public function skipAction(): Action
    {
        return Action::make('skip')
            ->label('Skip the setup for now')
            ->visible(fn (): bool => ! SetupStatus::current() instanceof SetupStatus)
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('danger')
            ->modalHeading('ChatVia.ai does not work without these')
            ->modalDescription(fn (): string => sprintf(
                'Still missing: %s. Until they are set up, agents cannot answer, sources cannot be trained and search does not work. You can finish the setup later from Settings.',
                implode(', ', app(SetupRequirements::class)->missing()) ?: 'nothing',
            ))
            ->modalSubmitActionLabel('Skip anyway')
            ->color('danger')
            ->action(function (PlatformSettings $settings): void {
                $settings->setup_status = SetupStatus::Skipped->value;
                $settings->save();

                $this->redirect(Filament::getUrl());
            });
    }

    // --- Checks ----------------------------------------------------------------

    /**
     * The server step's cards: checked when the step opens, and on "Check
     * again". The models' facts are loaded from models.dev first, so the
     * models added in the next steps already know how they reason.
     */
    public function checkServer(ServiceChecks $checks): void
    {
        rescue(fn () => app(SyncLlmFactsAction::class)->execute());

        $this->server = collect(self::SERVER_SERVICES)
            ->mapWithKeys(fn (Service $service): array => [$service->value => $checks->check($service)->toArray()])
            ->all();
        $this->serverDownConfirmed = false;
    }

    /** Stops Next on the server step while a service is down, until the admin agrees ({@see serverDownAction()}). */
    private function confirmServer(): void
    {
        if ($this->server === []) {
            $this->checkServer(app(ServiceChecks::class));
        }

        if ($this->servicesDown() === [] || $this->serverDownConfirmed) {
            return;
        }

        $this->mountAction('serverDown');

        throw new Halt;
    }

    /** @return list<Service> */
    private function servicesDown(): array
    {
        return array_values(array_filter(
            self::SERVER_SERVICES,
            fn (Service $service): bool => isset($this->server[$service->value])
                && ServiceStatusData::from($this->server[$service->value])->state === ServiceState::Down,
        ));
    }

    /** Creates the extension and rebuilds the keyword indexes; the database's own error when it cannot. */
    private function enablePgTextsearch(): void
    {
        try {
            app(EnablePgTextsearchAction::class)->execute();
        } catch (QueryException $exception) {
            Notification::make()
                ->danger()
                ->title('pg_textsearch could not be turned on')
                ->body(Str::limit($exception->getPrevious()?->getMessage() ?? $exception->getMessage(), 300))
                ->persistent()
                ->send();

            return;
        }

        $this->pgTextsearch = null;

        Notification::make()->success()->title('pg_textsearch is on')->body('Keyword search now ranks with BM25.')->send();
    }

    /**
     * The first step's check: read the model list with the key typed (or
     * saved), and, when embeddings need their own service, check that
     * service's key. A refused key or a server that cannot be reached stops
     * here.
     */
    private function checkConnection(): void
    {
        $state = $this->form->getRawState();

        if ($this->listedFor !== $this->fingerprint($state)) {
            $this->readModels($state);
        }

        if (in_array($this->listStatus, [ModelListStatus::KeyRefused->value, ModelListStatus::Unreachable->value], true)) {
            $field = ! $this->account($state) instanceof AiProvider && $this->listStatus === ModelListStatus::Unreachable->value ? 'data.server_url' : 'data.api_key';

            throw ValidationException::withMessages([$field => $this->listMessage]);
        }
    }

    /**
     * Runs a step's checks. A check that fails marks its field, often far
     * down the step; Filament scrolls to such a field only for its own
     * rules, so the same event is sent here, or Next seems to do nothing.
     */
    private function showingErrors(Closure $checks): void
    {
        try {
            $checks();
        } catch (ValidationException $exception) {
            $this->dispatch('form-validation-error', livewireId: $this->getId());

            throw $exception;
        }
    }

    /**
     * The models step's check: at least one chat model, and the embedding
     * model embeds a test text into the vector size the database stores.
     */
    private function checkModels(): void
    {
        $state = $this->form->getRawState();

        if ($this->chosenModels($state) === []) {
            throw ValidationException::withMessages([
                $this->models !== [] ? 'data.chat_models' : 'data.chat_model_id' => 'Pick at least one model for your agents.',
            ]);
        }

        $provider = $this->embeddingProvider($state);

        if (! $provider instanceof EmbeddingProvider) {
            return;
        }

        $keyField = $this->earlierKeyField('embedding_provider', $state) ?? 'embedding_api_key';
        $key = $state[$keyField] ?? null;
        $model = filled($state['embedding_model'] ?? null) ? (string) $state['embedding_model'] : null;
        $fingerprint = hash('sha256', implode("\n", [$provider->value, (string) $model, (string) $key]));

        if ($this->embeddingCheckedFor === $fingerprint) {
            return;
        }

        $result = app(EmbeddingCheck::class)->check($provider, $model, filled($key) ? (string) $key : null, $this->storedDimensions());

        if (! $result->ok()) {
            $field = $result->status === EmbeddingCheckStatus::KeyRefused ? "data.{$keyField}" : 'data.embedding_model';

            throw ValidationException::withMessages([$field => $result->message($provider->label(), $this->storedDimensions())]);
        }

        $this->embeddingCheckedFor = $fingerprint;
    }

    /**
     * The models step's check of the reranker: it ranks a test question with
     * the model and key chosen ({@see RerankerCheck}). A key typed in an
     * earlier step was checked there, so a refusal then points at the model.
     */
    private function checkReranker(): void
    {
        $state = $this->form->getRawState();
        $chosen = $this->rerankerData($state);

        if (! $chosen->isOn()) {
            return;
        }

        $earlier = $this->earlierKeyField('reranker', $state);
        $keyField = $chosen->driver === self::CUSTOM ? 'rerank_custom_key' : ($earlier ?? 'rerank_api_key');
        $reranker = new RerankerSetupData($chosen->driver, $chosen->model, $this->filled($state, $keyField), $chosen->url);
        $fingerprint = hash('sha256', implode("\n", [$reranker->driver, (string) $reranker->model, (string) $reranker->apiKey, (string) $reranker->url]));

        if ($this->rerankerCheckedFor === $fingerprint) {
            return;
        }

        $check = app(RerankerCheck::class)->check($reranker);

        if (! $check->connected) {
            $field = match (true) {
                $check->keyRefused && $earlier === null && filled($reranker->apiKey) => $keyField,
                $reranker->driver === self::CUSTOM => 'rerank_custom_url',
                default => "{$reranker->driver}_rerank_model",
            };

            throw ValidationException::withMessages(["data.{$field}" => $check->message]);
        }

        $this->rerankerCheckedFor = $fingerprint;
    }

    /** The documents step's check: the reader's key (and Azure's endpoint) work. */
    private function checkReader(): void
    {
        $state = $this->form->getRawState();
        $reader = AiProvider::tryFrom((string) ($state['document_reader'] ?? ''));

        if (! $reader instanceof AiProvider) {
            return;
        }

        $earlier = $this->earlierKeyField('document_reader', $state);
        $key = $state[$earlier ?? 'document_api_key'] ?? null;
        $endpoint = $reader === AiProvider::Azure ? ($state['document_endpoint'] ?? null) : null;

        // Nothing new typed, and the saved key was checked when it was saved.
        if (blank($key) && blank($endpoint) && $reader->isConnected()) {
            return;
        }

        $check = app(AiProviderCheck::class)->check($reader, filled($key) ? (string) $key : null, filled($endpoint) ? (string) $endpoint : null);

        if (! $check->connected) {
            $field = match (true) {
                ! $check->keyRefused && $reader === AiProvider::Azure => 'data.document_endpoint',
                default => 'data.'.($earlier ?? 'document_api_key'),
            };

            throw ValidationException::withMessages([$field => $check->message]);
        }

        // It works, with a catch: the free Azure tier reads 2 pages a file.
        if ($check->warning !== null) {
            Notification::make()->warning()->title("{$reader->label()} works, with a catch")->body($check->warning)->persistent()->send();
        }
    }

    /** Stops Next on "Not now" for a document reader until the admin agrees ({@see noReaderAction()}). */
    private function confirmNoReader(): void
    {
        if (($this->form->getRawState()['document_reader'] ?? null) !== self::NOT_NOW || $this->noReaderConfirmed) {
            return;
        }

        $this->mountAction('noReader');

        throw new Halt;
    }

    /** Moves the wizard on from the step with this label, as Next does. */
    private function leaveStep(string $label): void
    {
        $wizard = $this->form->getComponent(fn (mixed $component): bool => $component instanceof Wizard, withActions: false);

        if (! $wizard instanceof Wizard) {
            return;
        }

        $steps = array_values(array_filter($wizard->getChildSchema()->getComponents(), fn (mixed $component): bool => $component instanceof Step));
        $index = array_find_key($steps, fn (Step $step): bool => $step->getLabel() === $label);

        if (is_int($index)) {
            $wizard->nextStep($index);
        }
    }

    /** The websites step's check: Firecrawl accepts the key typed. */
    private function checkFirecrawl(): void
    {
        $state = $this->form->getRawState();

        if (($state['website_import'] ?? null) !== self::FIRECRAWL || blank($state['firecrawl_api_key'] ?? null)) {
            return;
        }

        $check = app(FirecrawlCheck::class)->check((string) $state['firecrawl_api_key']);

        if (! $check->connected) {
            throw ValidationException::withMessages(['data.firecrawl_api_key' => $check->message]);
        }
    }

    /**
     * The check of a step that picks an AI provider's service (speech to
     * text, voice): its key works, the one typed here or in an earlier step,
     * or the saved one.
     */
    private function checkServiceKey(string $choice, string $keyField): void
    {
        $state = $this->form->getRawState();
        $service = AiProvider::tryFrom((string) ($state[$choice] ?? ''));

        if (! $service instanceof AiProvider) {
            return;
        }

        $earlier = $this->earlierKeyField($choice, $state);
        $key = $state[$earlier ?? $keyField] ?? null;

        // Nothing new typed, and the saved key was checked when it was saved.
        if (blank($key) && $service->isConnected()) {
            return;
        }

        $check = app(AiProviderCheck::class)->check($service, filled($key) ? (string) $key : null);

        if (! $check->connected) {
            throw ValidationException::withMessages(['data.'.($earlier ?? $keyField) => $check->message]);
        }
    }

    /** The storage step's check: S3-compatible storage writes, reads and deletes a test file. */
    private function checkStorage(): void
    {
        $storage = $this->storageData($this->form->getRawState());

        if (! $storage->usesS3()) {
            return;
        }

        $error = app(StorageCheck::class)->check(new StorageSetupData(
            driver: 's3',
            endpoint: $storage->endpoint,
            region: $storage->region,
            key: $storage->key,
            secret: $storage->secret ?? config('filesystems.disks.s3.secret'),
            pathStyle: $storage->pathStyle,
            bucket: $storage->bucket,
        ));

        if ($error !== null) {
            throw ValidationException::withMessages(['data.s3_bucket' => $error]);
        }
    }

    /**
     * Read the chosen provider's model list with what is typed, and suggest
     * a model from it.
     *
     * @param  array<string, mixed>  $state
     */
    private function readModels(array $state): void
    {
        $this->forgetList();
        $this->listedFor = $this->fingerprint($state);

        $draft = $this->draftProvider($state);
        $adapter = app(ProviderAdapters::class)->for($draft->kind);
        $list = app(ModelListing::class)->list($draft);

        $this->listStatus = $list->status->value;
        $this->listMessage = $list->message($this->providerLabel($state['provider'] ?? null), $adapter->baseUrl($draft));

        Cache::put($this->modelCacheKey(), collect($list->models)->keyBy('id')->map(fn (RemoteModelData $model): array => $model->toArray())->all(), now()->addMinutes(10));

        $this->models = collect($list->models)->mapWithKeys(fn (RemoteModelData $model): array => [
            $model->id => $model->name === $model->id ? $model->id : "{$model->name} ({$model->id})",
        ])->all();

        $presets = app(ModelPresets::class);
        $account = $this->account($state);
        $suggested = array_map(fn (RemoteModelData $model): string => $model->id, $presets->chatModels($account, $list->models));

        if ($suggested !== [] && blank($this->data['chat_models'] ?? null)) {
            $this->data['chat_models'] = $suggested;
            $this->data['default_model'] = $suggested[0];
        }

        if (blank($this->data['quick_task_model'] ?? null)) {
            $this->data['quick_task_model'] = $presets->quickTaskModel($account, $list->models)?->id;
        }

        $vision = $presets->visionModel($account, $list->models);
        $this->listsVisionModel = $vision instanceof RemoteModelData;

        if (blank($this->data['vision_model'] ?? null)) {
            $this->data['vision_model'] = $vision?->id;
        }
    }

    // --- Helpers -------------------------------------------------------------

    /**
     * The chosen chat models, as the list described them (or by name only,
     * when a server lists none).
     *
     * @param  array<string, mixed>  $state
     * @return list<RemoteModelData>
     */
    private function chosenModels(array $state): array
    {
        $ids = $this->models !== []
            ? array_values(array_filter((array) ($state['chat_models'] ?? []), filled(...)))
            : array_filter([trim((string) ($state['chat_model_id'] ?? ''))], filled(...));

        return array_values(array_map(fn (string $id): RemoteModelData => $this->listedModel($id) ?? new RemoteModelData($id, $id), $ids));
    }

    /**
     * A model picked in one of the lists (quick tasks, image descriptions),
     * as the list described it; none when nothing is picked or the provider
     * lists no models.
     *
     * @param  array<string, mixed>  $state
     */
    private function listedChoice(array $state, string $field): ?RemoteModelData
    {
        $id = trim((string) ($state[$field] ?? ''));

        return $id === '' || $this->models === [] ? null : $this->listedModel($id) ?? new RemoteModelData($id, $id);
    }

    /**
     * The help under "Model for image descriptions": what it does, and, when
     * nothing is picked, that image descriptions stay off and why.
     */
    private function visionHelp(Get $get): string
    {
        $does = 'Describes the pictures inside PDF files and other documents, so search can find what they show. Needs a model that reads images.';

        if (filled($get('vision_model'))) {
            return $does;
        }

        $why = $this->listsVisionModel
            ? 'With none picked, image descriptions are off.'
            : 'None of the recommended models of this provider reads images, so image descriptions are off.';

        return "{$does} {$why} The text of your documents is still read.";
    }

    /**
     * The reranker chosen, with the key typed for it here: a key of an
     * earlier step is saved with that step.
     *
     * @param  array<string, mixed>  $state
     */
    private function rerankerData(array $state): RerankerSetupData
    {
        return match ($driver = (string) ($state['reranker'] ?? '')) {
            AiProvider::OpenRouter->value, AiProvider::Cohere->value => new RerankerSetupData(
                driver: $driver,
                model: $this->filled($state, "{$driver}_rerank_model"),
                apiKey: $this->reusesEarlierKey('reranker', $state) ? null : $this->filled($state, 'rerank_api_key'),
            ),
            self::CUSTOM => new RerankerSetupData(
                driver: self::CUSTOM,
                model: $this->filled($state, 'rerank_custom_model'),
                apiKey: $this->filled($state, 'rerank_custom_key'),
                url: $this->filled($state, 'rerank_custom_url'),
            ),
            default => new RerankerSetupData(self::NOT_NOW),
        };
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function storageData(array $state): StorageSetupData
    {
        return new StorageSetupData(
            driver: ($state['storage_driver'] ?? 'local') === 's3' ? 's3' : 'local',
            endpoint: $this->filled($state, 's3_endpoint'),
            region: $this->filled($state, 's3_region'),
            key: $this->filled($state, 's3_key'),
            secret: $this->filled($state, 's3_secret'),
            pathStyle: (bool) ($state['s3_path_style'] ?? false),
            bucket: $this->filled($state, 's3_bucket'),
        );
    }

    /**
     * A trimmed value of the form, or null when it is blank.
     *
     * @param  array<string, mixed>  $state
     */
    private function filled(array $state, string $key): ?string
    {
        $value = trim((string) ($state[$key] ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * The chat providers, then a server of your own.
     *
     * @return array<string, string>
     */
    private function providerOptions(): array
    {
        return [
            ...collect($this->chatAccounts())->mapWithKeys(fn (AiProvider $provider): array => [$provider->value => $provider->label()])->all(),
            self::CUSTOM => 'Your own server',
        ];
    }

    /**
     * One short sentence about each provider, under its name.
     *
     * @return array<string, string>
     */
    private function providerDescriptions(): array
    {
        return [
            ...collect($this->chatAccounts())->mapWithKeys(fn (AiProvider $provider): array => [$provider->value => $provider->description()])->all(),
            self::CUSTOM => 'vLLM, Ollama, LM Studio or another service with an OpenAI-compatible API.',
        ];
    }

    /**
     * The logo or icon on each provider's card, as in Settings → AI providers.
     *
     * @return array<string, string|Heroicon>
     */
    private function providerIcons(): array
    {
        return [
            ...collect($this->chatAccounts())->mapWithKeys(fn (AiProvider $provider): array => [$provider->value => AiProviderIcon::of($provider)])->all(),
            self::CUSTOM => Heroicon::OutlinedServerStack,
        ];
    }

    /**
     * What each provider's key gives: its card's tags.
     *
     * @return array<string, list<string>>
     */
    private function providerTags(): array
    {
        return [
            ...collect($this->chatAccounts())->mapWithKeys(fn (AiProvider $provider): array => [
                $provider->value => array_map(fn (AiCapability $capability): string => $capability->label(), $provider->capabilities()),
            ])->all(),
            self::CUSTOM => [AiCapability::Chat->label()],
        ];
    }

    /**
     * The providers with chat models, by name: the order of their cards.
     *
     * @return list<AiProvider>
     */
    private function chatAccounts(): array
    {
        return collect(AiProvider::cases())
            ->filter(fn (AiProvider $provider): bool => $provider->can(AiCapability::Chat))
            ->sortBy(fn (AiProvider $provider): string => $provider->label(), SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /** "chat · embeddings · speech to text" */
    private function capabilityList(AiProvider $provider): string
    {
        return implode(' · ', array_map(fn (AiCapability $capability): string => $capability->label(), $provider->capabilities()));
    }

    private function providerLabel(mixed $value): string
    {
        return AiProvider::tryFrom((string) $value)?->label() ?? 'your server';
    }

    /**
     * The account of the chosen provider, or null for a server of your own.
     *
     * @param  array<string, mixed>  $state
     */
    private function account(array $state): ?AiProvider
    {
        return AiProvider::tryFrom((string) ($state['provider'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function chatKind(array $state): LlmProviderKind
    {
        return $this->account($state)?->chatKind() ?? LlmProviderKind::Custom;
    }

    /**
     * The account of the service chosen in a field: null for none, a server
     * of your own, or an embedding service the provider makes itself.
     *
     * @param  Get|array<string, mixed>  $state
     */
    private function chosenAccount(string $choice, Get|array $state): ?AiProvider
    {
        $value = fn (string $key): mixed => $state instanceof Get ? $state($key) : ($state[$key] ?? null);

        return $choice === 'embedding_provider'
            ? $this->embeddingProvider($state)?->account()
            : AiProvider::tryFrom((string) $value($choice));
    }

    /**
     * The key field of an earlier step that chose the same account as this
     * field, or null.
     *
     * @param  Get|array<string, mixed>  $state
     */
    private function earlierKeyField(string $choice, Get|array $state): ?string
    {
        $account = $this->chosenAccount($choice, $state);

        if (! $account instanceof AiProvider) {
            return null;
        }

        foreach (self::KEY_FIELDS as $earlier => [$keyField]) {
            if ($earlier === $choice) {
                return null;
            }

            if ($this->chosenAccount($earlier, $state) === $account) {
                return $keyField;
            }
        }

        return null;
    }

    /**
     * Whether the service chosen in a field uses a key of an earlier step.
     *
     * @param  Get|array<string, mixed>  $state
     */
    private function reusesEarlierKey(string $choice, Get|array $state): bool
    {
        return $this->earlierKeyField($choice, $state) !== null;
    }

    /**
     * Whether the service chosen in a field uses the key saved already (in
     * Settings → AI providers), as no earlier step has its account.
     *
     * @param  Get|array<string, mixed>  $state
     */
    private function usesSavedKey(string $choice, Get|array $state): bool
    {
        $account = $this->chosenAccount($choice, $state);

        return $account instanceof AiProvider && ! $this->reusesEarlierKey($choice, $state) && $account->isConnected();
    }

    /** Says which key the service chosen in a field uses, when it is not typed here. */
    private function keyCallout(string $choice): Callout
    {
        return Callout::make(function (Get $get) use ($choice): string {
            $account = $this->chosenAccount($choice, $get)?->label();
            $step = $this->stepWithTypedKey($choice, $get);

            return $step !== null
                ? "Uses the {$account} key you entered in {$step}"
                : "Uses your saved {$account} key".($this->chosenAccount($choice, $get)?->needsBaseUrl() ? ' and endpoint' : '');
        })
            ->description(fn (Get $get): ?string => $this->stepWithTypedKey($choice, $get) === null ? 'You can change it in Settings → AI providers.' : null)
            ->icon(Heroicon::OutlinedKey)
            ->info()
            ->visible(fn (Get $get): bool => $this->reusesEarlierKey($choice, $get) || $this->usesSavedKey($choice, $get));
    }

    /** The earlier step where the key of this field's account was typed; null when it was left blank there (the saved one is used) or not asked. */
    private function stepWithTypedKey(string $choice, Get $get): ?string
    {
        $earlier = $this->earlierKeyField($choice, $get);

        return $earlier !== null && filled($get($earlier))
            ? array_find(self::KEY_FIELDS, fn (array $field): bool => $field[0] === $earlier)[1] ?? null
            : null;
    }

    /** The key of the service chosen in a field, asked only when no earlier step has it and none is saved. */
    private function serviceKeyField(string $name, string $choice, string $linkAction): TextInput
    {
        $account = fn (Get $get): ?AiProvider => $this->chosenAccount($choice, $get);

        return TextInput::make($name)
            ->label(fn (Get $get): string => $account($get)?->label().' API key')
            ->password()
            ->revealable()
            ->autocomplete('new-password')
            // Azure with its key saved but not its endpoint still shows the key, as optional.
            ->required(fn (Get $get): bool => blank(config((string) $account($get)?->apiKeyConfig())))
            ->placeholder(fn (Get $get): ?string => filled(config((string) $account($get)?->apiKeyConfig())) ? '•••••••• saved, leave blank to keep' : null)
            ->helperText('Stored encrypted. It is never shown again.')
            ->hintAction(fn (Get $get): ?Action => ($url = $account($get)?->keysUrl()) === null ? null : Action::make($linkAction)
                ->label('Get a key')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->iconPosition('after')
                ->url($url, shouldOpenInNewTab: true))
            ->visible(fn (Get $get): bool => $account($get) instanceof AiProvider
                && ! $this->reusesEarlierKey($choice, $get)
                && ! $this->usesSavedKey($choice, $get));
    }

    /**
     * The speech to text services: the providers that do it, as option values.
     *
     * @return list<string>
     */
    private function speechOptions(): array
    {
        return array_values(array_map(
            fn (AiProvider $provider): string => $provider->value,
            array_filter(AiProvider::cases(), fn (AiProvider $provider): bool => $provider->can(AiCapability::Speech)),
        ));
    }

    /**
     * The voice conversation services: the providers that hold live calls,
     * as option values.
     *
     * @return list<string>
     */
    private function voiceOptions(): array
    {
        return array_values(array_map(
            fn (AiProvider $provider): string => $provider->value,
            array_filter(AiProvider::cases(), fn (AiProvider $provider): bool => $provider->can(AiCapability::Voice)),
        ));
    }

    /**
     * The embedding service picked in the Models step (it starts on the AI
     * provider's own when that fits); null once sources are trained (it
     * stays).
     *
     * @param  Get|array<string, mixed>  $state
     */
    private function embeddingProvider(Get|array $state): ?EmbeddingProvider
    {
        if ($this->trained()) {
            return null;
        }

        return EmbeddingProvider::tryFrom((string) ($state instanceof Get ? $state('embedding_provider') : ($state['embedding_provider'] ?? null)));
    }

    /**
     * The provider typed so far, unsaved, with the key typed or (left blank)
     * its account's saved one.
     *
     * @param  array<string, mixed>  $state
     */
    private function draftProvider(array $state): LlmProvider
    {
        $account = $this->account($state);

        return new LlmProvider([
            'kind' => $this->chatKind($state),
            'name' => $account?->label() ?? (string) ($state['server_name'] ?? 'Server'),
            'base_url' => $account instanceof AiProvider ? null : rtrim((string) ($state['server_url'] ?? ''), '/'),
            'api_key' => $this->filled($state, 'api_key'),
        ]);
    }

    /**
     * What the model list depends on: the provider, address and key typed.
     *
     * @param  array<string, mixed>  $state
     */
    private function fingerprint(array $state): string
    {
        return hash('sha256', implode("\n", [$state['provider'] ?? '', $state['server_url'] ?? '', $state['api_key'] ?? '']));
    }

    private function forgetList(): void
    {
        $this->models = [];
        $this->listStatus = null;
        $this->listMessage = null;
        $this->listedFor = null;
        $this->data['chat_models'] = [];
        $this->data['default_model'] = null;
        $this->data['quick_task_model'] = null;
        $this->data['vision_model'] = null;
        $this->listsVisionModel = false;
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
     * The provider to start on: one whose key is saved already, else
     * OpenRouter, whose one key gives chat, embeddings and reranking.
     */
    private function suggestedProvider(): AiProvider
    {
        return array_find(AiProvider::cases(), fn (AiProvider $provider): bool => $provider->can(AiCapability::Chat) && $provider->isConnected())
            ?? AiProvider::OpenRouter;
    }

    /**
     * The provider's own embedding service when it fits, else the one in use
     * when its key is saved, else the first that fits with a saved key, else
     * the first that fits.
     */
    private function suggestedEmbedding(?AiProvider $provider): ?EmbeddingProvider
    {
        $fitting = EmbeddingProvider::fitting();
        $current = EmbeddingProvider::configured();
        $own = $provider instanceof AiProvider ? EmbeddingProvider::fittingFor($provider) : null;

        return match (true) {
            $own instanceof EmbeddingProvider => $own,
            in_array($current, $fitting, true) && $current->account()->isConnected() => $current,
            default => array_find($fitting, fn (EmbeddingProvider $fits): bool => $fits->account()->isConnected()) ?? $fitting[0] ?? null,
        };
    }

    /**
     * The reranker to start on: OpenRouter when it is the provider (its key
     * covers it), else the one set up already, else none.
     */
    private function suggestedReranker(?AiProvider $provider): string
    {
        $saved = (string) config('chatvia.ai.reranker');
        $saved = $saved === 'auto' ? app(RerankerFactory::class)->autoDriver() : $saved;

        return match (true) {
            $provider === AiProvider::OpenRouter => AiProvider::OpenRouter->value,
            AiProvider::tryFrom($saved)?->isConnected() === true => $saved,
            $saved === self::CUSTOM && filled(config('chatvia.ai.rerank.custom.url')) => self::CUSTOM,
            default => self::NOT_NOW,
        };
    }

    /** A reader already connected, else Mistral when it is the chat provider, else none. */
    private function suggestedReader(?AiProvider $provider): string
    {
        return match (true) {
            AiProvider::Mistral->isConnected(), $provider === AiProvider::Mistral => AiProvider::Mistral->value,
            AiProvider::Azure->isConnected() => AiProvider::Azure->value,
            default => self::NOT_NOW,
        };
    }

    private function trained(): bool
    {
        return once(fn (): bool => Chunk::query()->exists());
    }

    /**
     * An embedding column as the database has it, or null before the first
     * migration. Every one has the same type: `chatvia:search:check-embedding-storage`
     * stops a start otherwise.
     */
    private function storedColumn(): ?EmbeddingColumnData
    {
        return $this->storedColumn ??= app(StoredEmbeddingColumnsQuery::class)->get()->first();
    }

    /** The vector size the database stores, which an embedding model must return. */
    private function storedDimensions(): int
    {
        return $this->storedColumn()?->dimensions() ?? app(EmbeddingStorage::class)->dimensions();
    }
}
