<?php

declare(strict_types=1);

namespace App\Admin\Pages;

use App\Admin\AdminPanelProvider;
use App\Admin\Forms\CardRadio;
use App\Admin\Forms\ModelPicker;
use App\Admin\RequireSetup;
use App\Admin\Settings\Actions\ActivateLicenseAction;
use App\Admin\Settings\Actions\CompleteSetupAction;
use App\Admin\Settings\AiProviderCheck;
use App\Admin\Settings\AiProviderIcon;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\BrandSettings;
use App\Admin\Settings\ChatModelCheck;
use App\Admin\Settings\CreditLicense;
use App\Admin\Settings\Data\MailSetupData;
use App\Admin\Settings\Data\ModelCheckData;
use App\Admin\Settings\Data\RerankerSetupData;
use App\Admin\Settings\Data\S3BucketData;
use App\Admin\Settings\Data\SetupData;
use App\Admin\Settings\Data\StorageSetupData;
use App\Admin\Settings\FirecrawlCheck;
use App\Admin\Settings\LicenseStatus;
use App\Admin\Settings\LlmTaskText;
use App\Admin\Settings\MailField;
use App\Admin\Settings\MailProvider;
use App\Admin\Settings\MailSettings;
use App\Admin\Settings\ModelLogo;
use App\Admin\Settings\ObservabilitySettings;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\RerankerCheck;
use App\Admin\Settings\SetupRequirements;
use App\Admin\Settings\SetupStatus;
use App\Admin\Settings\StorageCheck;
use App\Admin\Settings\StorageSettings;
use App\Admin\Settings\TaskModelPreset;
use App\Admin\Settings\ToolRecording;
use App\Admin\Status\Data\ServiceStatusData;
use App\Admin\Status\Service;
use App\Admin\Status\ServiceChecks;
use App\Admin\Status\ServiceState;
use Closure;
use Domain\Analytics\Services\AnalyticsDatabase;
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
use Domain\Llm\Data\Catalog\ModelFactsData;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\AiCapability;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Enums\ModelListStatus;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\ModelListing;
use Domain\Llm\Services\ModelPresets;
use Domain\Llm\Services\ModelsDevCatalog;
use Domain\Llm\Services\ProviderAdapters;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Async;
use Livewire\Attributes\Renderless;
use Support\License\LicenseFeature;

/**
 * The setup of a new install: everything the app needs to work, step by
 * step. Each key is checked before the next step and saved once.
 *
 * 1. Server: the services ChatVia.ai runs on (database, Redis, queue,
 *    scheduler, live updates), checked live as on the Status page; keyword
 *    search: pg_textsearch is recommended, with why and how to get it, and
 *    turned on here when the database has it loaded; and pg_partman,
 *    which analytics needs, with a warning and a link to the docs when missing.
 *    Next with a service down asks first ({@see serverDownAction()}).
 * 2. AI provider: the main provider and its key (its model list checks it);
 *    more providers can be added later in Settings → AI providers.
 * 3. Models: the chat models agents can use (one or more, one of them the
 *    default for new agents; the provider's recommended ones are picked,
 *    {@see ModelPresets}), the model for quick tasks (guardrails, follow-up
 *    questions, titles…: a small one when the provider has one, else
 *    the chat model), the model for image descriptions (one that reads
 *    images; with none, image descriptions are off), the model for chat
 *    file uploads (one that reads PDF files and images; with none, chatters
 *    cannot attach files), the embedding service
 *    (it starts on the provider's own when that fits; any other may be
 *    picked, with its key) and model (picked from OpenRouter's list there),
 *    checked with a test embedding, and the
 *    reranker: OpenRouter (its live list of rerankers), Cohere, a server
 *    of your own or none, checked with a test question.
 * 4. Ingestion, in three sections. Document reader: Mistral OCR, Azure
 *    Document Intelligence or none (the default); without a reader, PDF,
 *    Word, slides and images cannot be uploaded. Web scraper: Firecrawl
 *    or none (the default); without it, websites cannot be added as
 *    knowledge. AI enhancement: on (the default) or off, for files, web
 *    pages, texts and the summary of each source together; Settings →
 *    Knowledge and AI models turn them on one by one.
 * 5. Voice, in two sections. Speech to text: Scaleway AI, OpenAI or none
 *    (the default); without it, audio and video files cannot be uploaded
 *    and voice messages stay audio. Voice conversations: OpenAI's Realtime
 *    API or none (the default); it needs an OpenAI key even when agents
 *    answer with another provider, and each agent still turns calls on in
 *    its own setup.
 * 6. Storage: private files, public images and backups, each on this
 *    server (the default) or in an S3-compatible bucket of its own (each
 *    bucket checked with a test file).
 * 7. Mailer: none (the default) or a mail service. Without one nobody
 *    receives sign-up, password or invitation emails.
 * 8. Observability: Spectra, the log of every AI call (the default), or
 *    none. What it saves of each call is changed in Settings →
 *    Observability.
 * 9. App: the app's name, which the sender name of emails follows until it
 *    is changed in the Mailer step; and the license (optional): what a key
 *    adds to a free install, with a link to buy one, and the key, activated
 *    at chatvia.ai and saved on Next ({@see activateLicense()}), the one
 *    thing saved before Finish setup. On an address only this computer
 *    opens, the field says the key is tied to it until removed.
 * 10. Final: "Congratulations", a link to the workspace sign-in page for
 *    after Finish setup, and the Finish setup button itself, in the middle
 *    in place of the action bar.
 *
 * Each step starts on the safe choice ("Disable …", "This server", Spectra
 * on) unless it is set up already: what `.env` suggests is not a choice. The one
 * exception is the reranker with OpenRouter as the provider: the same key
 * covers it, so it starts on OpenRouter. "Disable …"
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

    /** The sections of the models step that "Test" checks ({@see testAction()}). */
    private const string TEST_CHAT = 'chat';

    private const string TEST_EMBEDDINGS = 'embeddings';

    private const string TEST_RERANKER = 'reranker';

    private const string TEST_IMAGES = 'images';

    private const string TEST_FILES = 'files';

    /** The sections Next on the models step tests, each under its title ({@see testModelsAction()}). */
    private const array STEP_TESTS = [self::TEST_CHAT, self::TEST_EMBEDDINGS, self::TEST_RERANKER];

    /** The option of no document reader, web scraper or speech to text. */
    private const string NOT_NOW = 'none';

    /**
     * The fields that choose a service, in step order, each with the field of
     * its key and the step it is on. A service whose account an earlier step
     * chose uses the key typed there.
     */
    private const array KEY_FIELDS = [
        'provider' => ['api_key', 'AI provider'],
        'embedding_provider' => ['embedding_api_key', 'Models'],
        'reranker' => ['rerank_api_key', 'Models'],
        'document_reader' => ['document_api_key', 'Ingestion'],
        'transcriber' => ['transcription_api_key', 'Voice'],
        'voice' => ['voice_api_key', 'Voice'],
    ];

    /** The label of the server step, which Next leaves once a service down is confirmed. */
    private const string SERVER_STEP = 'Server';

    /** The services the server step checks: what runs beside the web server. Storage and email have steps of their own. */
    private const array SERVER_SERVICES = [Service::Database, Service::Redis, Service::Queue, Service::Scheduler, Service::Realtime, Service::ModelDetails];

    /** The label of the models step, which Next leaves once each model answered its test. */
    private const string MODELS_STEP = 'Models';

    /** The label of the ingestion step, which Next leaves once "Disable document reader" is confirmed. */
    private const string INGESTION_STEP = 'Ingestion';

    /** The option of AI enhancement of what agents learn. */
    private const string ENHANCE = 'enhance';

    /** The option of the web scraper with Firecrawl. */
    private const string FIRECRAWL = 'firecrawl';

    /** The option of recording AI calls with Spectra. */
    private const string SPECTRA = 'spectra';

    #[\Override]
    protected Width|string|null $maxWidth = Width::SixExtraLarge;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, string> The models the chosen provider lists: id => label. */
    public array $models = [];

    /** @var list<string> The provider's recommended chat models in the list ({@see ModelPresets::chatModels()}). */
    public array $suggestedModels = [];

    /** @var list<string> The listed models models.dev says cannot call tools: agents that use them answer without tools. */
    public array $modelsWithoutTools = [];

    /** @var array<string, bool> Whether each listed model reads images, for the models the provider or models.dev says. */
    public array $modelsReadingImages = [];

    /** Whether the list has a recommended model that reads images ({@see ModelPresets::visionModel()}). */
    public bool $listsVisionModel = false;

    /** @var list<string> The listed models that read PDF files and images, as the provider or models.dev says. */
    public array $modelsReadingFiles = [];

    /** Whether the list has a recommended model for chat file uploads ({@see ModelPresets::fileModel()}). */
    public bool $listsFileModel = false;

    /** How reading the model list went ({@see ModelListStatus}), or null before it was read. */
    public ?string $listStatus = null;

    /** Why there is no list, or null. */
    public ?string $listMessage = null;

    /** The provider, address and key the list was last read with, so Next does not read it again. */
    public ?string $listedFor = null;

    /** @var array<string, array<string, mixed>> The server step's checks, by {@see Service} value. */
    public array $server = [];

    /** Whether the admin agreed to go on with a service down, so Next does not warn again. */
    public bool $serverDownConfirmed = false;

    /** Whether the admin agreed to go on without a document reader, so Next does not warn again. */
    public bool $noReaderConfirmed = false;

    /** How ready pg_textsearch is, read once per request. */
    private ?PgTextsearchState $pgTextsearch = null;

    /** Whether the analytics database has pg_partman, read once per request. */
    private ?bool $partman = null;

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
            'describe_images' => false,
            'vision_model' => null,
            'chat_files' => false,
            'files_model' => null,
            'reranker' => $this->suggestedReranker($provider),
            'openrouter_rerank_model' => config('chatvia.ai.rerank.openrouter_model'),
            'cohere_rerank_model' => config('services.cohere.rerank_model'),
            'rerank_custom_url' => config('chatvia.ai.rerank.custom.url'),
            'rerank_custom_model' => config('chatvia.ai.rerank.custom.model'),
            'document_reader' => $this->suggestedReader($provider),
            'website_import' => filled(config('services.firecrawl.key')) ? self::FIRECRAWL : self::NOT_NOW,
            'enhancement' => filter_var(config('chatvia.ai.chunker.enhance'), FILTER_VALIDATE_BOOL) ? self::ENHANCE : self::NOT_NOW,
            'transcriber' => collect($this->speechOptions())->containsStrict(app(AiSettings::class)->transcriber) ? app(AiSettings::class)->transcriber : self::NOT_NOW,
            'voice' => collect($this->voiceOptions())->containsStrict(app(AiSettings::class)->voice) ? app(AiSettings::class)->voice : self::NOT_NOW,
            'storage_driver' => app(StorageSettings::class)->driver === 's3' ? 's3' : 'local',
            'public_files' => filled(app(StorageSettings::class)->s3_public_bucket) ? 'bucket' : 'local',
            'backup_files' => match (true) {
                app(StorageSettings::class)->backupsInPrivateBucket() => 'private',
                filled(app(StorageSettings::class)->s3_backup_bucket) => 'bucket',
                default => 'local',
            },
            'mailer' => $mailer === MailProvider::Sendmail ? MailProvider::Log->value : $mailer->value,
            'from_address' => config('mail.from.address'),
            'app_name' => config('app.name'),
            'from_name' => config('app.name'),
            'observability' => app(ObservabilitySettings::class)->spectra_enabled === false ? self::NOT_NOW : self::SPECTRA,
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
                $this->ingestionStep(),
                $this->voiceStep(),
                $this->storageStep(),
                $this->emailStep(),
                $this->observabilityStep(),
                $this->appStep(),
                $this->finalStep(),
            ])
                ->contained(false)
                ->extraAttributes(['class' => 'cv-setup-wizard'])
                // The last step has no action bar: its own Finish setup button replaces it.
                ->extraAlpineAttributes(['x-bind:class' => "{ 'cv-setup-wizard--last': isLastStep() }"])
                // Shown on every step but the last, in the bar with Back and Next.
                ->cancelAction($this->wayOutLink()),
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
            server: $account instanceof AiProvider ? null : new CustomLlmServerData(
                name: (string) $state['server_name'],
                baseUrl: (string) $state['server_url'],
                apiKey: $this->filled($state, 'api_key'),
            ),
            chatEndpoint: $account?->needsBaseUrl() ? $this->filled($state, 'chat_endpoint') : null,
            quickTaskModel: $this->listedChoice($state, 'quick_task_model'),
            visionModel: (bool) ($state['describe_images'] ?? false) ? $this->listedChoice($state, 'vision_model') : null,
            filesModel: (bool) ($state['chat_files'] ?? false) ? $this->listedChoice($state, 'files_model') : null,
            embeddingProvider: $embedding,
            embeddingModel: $this->filled($state, 'embedding_model'),
            embeddingApiKey: $embedding instanceof EmbeddingProvider && ! $this->reusesEarlierKey('embedding_provider', $state) ? $this->filled($state, 'embedding_api_key') : null,
            reranker: $this->rerankerData($state),
            documentReader: $reader,
            documentApiKey: $reader instanceof AiProvider && ! $this->reusesEarlierKey('document_reader', $state) ? $this->filled($state, 'document_api_key') : null,
            documentEndpoint: $reader === AiProvider::Azure ? $this->filled($state, 'document_endpoint') : null,
            firecrawlApiKey: ($state['website_import'] ?? null) === self::FIRECRAWL ? $this->filled($state, 'firecrawl_api_key') : null,
            enhanceKnowledge: ($state['enhancement'] ?? null) !== self::NOT_NOW,
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
            recordAiCalls: ($state['observability'] ?? null) !== self::NOT_NOW,
            appName: $this->filled($state, 'app_name'),
        ));

        Cache::forget($this->modelCacheKey());

        Notification::make()
            ->success()
            ->title('ChatVia.ai is ready')
            ->body("New agents answer with {$model->displayName()}.")
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
                        View::make('admin.setup.server')->viewData(fn (): array => [
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
                    ->visible(fn (): bool => $this->pgTextsearchState() !== PgTextsearchState::Active)
                    ->schema([$this->keywordSearchCallout()]),
                Section::make('Analytics')
                    ->description('Every message is logged for the charts and reports, in a table split by month.')
                    ->icon(Heroicon::OutlinedChartBar)
                    ->visible(fn (): bool => ! $this->hasPartman())
                    ->schema([$this->analyticsCallout()]),
            ])
            ->afterValidation(fn () => $this->confirmServer());
    }

    private function connectStep(): Step
    {
        return Step::make('AI provider')
            ->description('Main AI provider')
            ->icon(Heroicon::OutlinedKey)
            ->schema([
                Section::make('Main AI provider')
                    ->description('Choose the main AI provider. Your agents use its models to answer. This is just the one to start with: you can add more providers later in Settings → AI providers.')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->schema($this->providerFields()),
            ])
            ->afterValidation(fn () => $this->showingErrors($this->checkConnection(...)));
    }

    private function modelsStep(): Step
    {
        return Step::make(self::MODELS_STEP)
            ->description('What agents answer with')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->schema([
                Callout::make(fn (Get $get): string => 'Connected to '.$this->providerLabel($get('provider')))
                    ->description(fn (Get $get): ?string => ($account = AiProvider::tryFrom((string) $get('provider'))) instanceof AiProvider
                        ? "This key gives you: {$this->capabilityList($account)}."
                        : null)
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->success(),
                Section::make($this->sectionTitle(self::TEST_CHAT))
                    ->description('The models agents can use. Each agent picks one; new agents start with the default. You can add more later in Settings → AI models.')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->key(self::TEST_CHAT, isInheritable: false)
                    ->headerActions([$this->testAction(self::TEST_CHAT)])
                    ->schema($this->chatModelFields()),
                Section::make($this->sectionTitle(self::TEST_EMBEDDINGS))
                    ->description(fn (Get $get): string => $this->embeddingDescription($get))
                    ->icon(Heroicon::OutlinedCubeTransparent)
                    ->key(self::TEST_EMBEDDINGS, isInheritable: false)
                    ->headerActions([$this->testAction(self::TEST_EMBEDDINGS)])
                    ->schema([...$this->embeddingServiceFields(), ...$this->embeddingModelFields()]),
                Section::make($this->sectionTitle(self::TEST_RERANKER))
                    ->description('Scores what search finds, and your tools, against each question, so agents use only the relevant ones.')
                    ->icon(Heroicon::OutlinedBarsArrowDown)
                    ->key(self::TEST_RERANKER, isInheritable: false)
                    ->headerActions([$this->testAction(self::TEST_RERANKER)])
                    ->schema($this->rerankerFields()),
            ])
            ->afterValidation(fn () => $this->showingErrors(function (): void {
                $this->checkChatModelsChosen();
                $this->confirmModels();
                $this->checkModels();
                $this->checkReranker();
            }));
    }

    private function ingestionStep(): Step
    {
        return Step::make(self::INGESTION_STEP)
            ->description('Documents and websites')
            ->icon(Heroicon::OutlinedInboxArrowDown)
            ->schema([
                Section::make('Document reader')
                    ->description('Reads PDF, Word, slides and images, with their tables, so agents can learn from them.')
                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                    ->schema([
                        CardRadio::make('document_reader')
                            ->hiddenLabel()
                            ->options([
                                AiProvider::Mistral->value => 'Mistral OCR',
                                AiProvider::Azure->value => 'Azure Document Intelligence',
                                self::NOT_NOW => 'Disable document reader',
                            ])
                            ->descriptions([
                                AiProvider::Mistral->value => 'Reads scans, images and tables, also the pictures inside Word and PowerPoint files. Each file is sent to Mistral directly.',
                                AiProvider::Azure->value => 'Reads scans, images and tables, but not the pictures inside Word and PowerPoint files. Each file is sent to Azure directly.',
                                self::NOT_NOW => 'PDF, Word, PowerPoint and image files are not read, so they cannot be uploaded. Only text, Markdown, Excel, CSV and ebook files are read.',
                            ])
                            ->icons([
                                AiProvider::Mistral->value => AiProviderIcon::of(AiProvider::Mistral),
                                AiProvider::Azure->value => AiProviderIcon::of(AiProvider::Azure),
                                self::NOT_NOW => Heroicon::OutlinedNoSymbol,
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
                            ->helperText('Your resource\'s endpoint, from the Azure portal. Use the standard tier (S0): the free one reads only 2 pages of each file.')
                            ->required(fn (Get $get): bool => blank(config('services.azure_document_intelligence.endpoint')))
                            ->visible(fn (Get $get): bool => $get('document_reader') === AiProvider::Azure->value && ! $this->usesSavedKey('document_reader', $get)),
                    ]),
                Section::make('Web scraper')
                    ->description('To learn from a website, agents need Firecrawl. It finds the pages of the site and reads each one, also pages built with JavaScript.')
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->schema([
                        CardRadio::make('website_import')
                            ->hiddenLabel()
                            ->options([self::FIRECRAWL => 'Firecrawl', self::NOT_NOW => 'Disable web scraper'])
                            ->descriptions([
                                self::FIRECRAWL => 'Pay per page read. You can also run your own Firecrawl server (Settings → Knowledge).',
                                self::NOT_NOW => 'People cannot add websites as knowledge. Files, texts and Q&A still work.',
                            ])
                            ->icons([
                                self::FIRECRAWL => asset('images/logos/services/firecrawl.svg'),
                                self::NOT_NOW => Heroicon::OutlinedNoSymbol,
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
                Section::make('AI enhancement')
                    ->description(new HtmlString(
                        'AI writes a short summary and likely questions for each part of what agents learn, so they find the right part more often. '
                        .'<a href="'.e(config('chatvia.docs_url').'/guide/how-it-works/knowledge#how-much-it-helps').'" target="_blank" rel="noopener" class="underline">See the test results</a>'
                    ))
                    ->icon(Heroicon::OutlinedSparkles)
                    ->schema([
                        CardRadio::make('enhancement')
                            ->hiddenLabel()
                            ->options([self::ENHANCE => 'Enable AI enhancement', self::NOT_NOW => 'Disable AI enhancement'])
                            ->descriptions([
                                self::ENHANCE => 'Recommended. Slower, and uses more tokens and AI calls, but agents find answers more accurately.',
                                self::NOT_NOW => 'Faster, and uses fewer tokens and AI calls, but agents find answers less accurately.',
                            ])
                            ->icons([
                                self::ENHANCE => Heroicon::OutlinedSparkles,
                                self::NOT_NOW => Heroicon::OutlinedNoSymbol,
                            ])
                            ->required()
                            ->live(),
                        Callout::make('AI enhancement is slower and uses more tokens')
                            ->description('It makes one AI call for each part of a source, so a 100-page PDF file makes about 100 calls and takes longer to learn. It uses the model for quick tasks from the Models step. You can turn it off later in Settings → Knowledge.')
                            ->icon(Heroicon::OutlinedExclamationTriangle)
                            ->warning()
                            ->visible(fn (Get $get): bool => $get('enhancement') !== self::NOT_NOW),
                    ]),
            ])
            ->afterValidation(fn () => $this->showingErrors(function (): void {
                $this->checkReader();
                $this->checkFirecrawl();
                $this->confirmNoReader();
            }));
    }

    private function voiceStep(): Step
    {
        $speech = array_map(AiProvider::from(...), $this->speechOptions());
        $voice = array_map(AiProvider::from(...), $this->voiceOptions());

        return Step::make('Voice')
            ->description('Speech to text and live calls')
            ->icon(Heroicon::OutlinedMicrophone)
            ->schema([
                Section::make('Speech to text')
                    ->description('Turns speech into text. Agents can then learn from audio and video files and search what is said in them, and voice messages become text.')
                    ->icon(Heroicon::OutlinedMicrophone)
                    ->schema([
                        CardRadio::make('transcriber')
                            ->hiddenLabel()
                            ->options([
                                ...collect($speech)->mapWithKeys(fn (AiProvider $service): array => [$service->value => $service->label()])->all(),
                                self::NOT_NOW => 'Disable speech to text',
                            ])
                            ->descriptions([
                                ...collect($speech)->mapWithKeys(fn (AiProvider $service): array => [$service->value => "Whisper on {$service->label()}."])->all(),
                                self::NOT_NOW => 'People cannot upload audio or video files, and voice messages are not turned into text.',
                            ])
                            ->icons([
                                ...collect($speech)->mapWithKeys(fn (AiProvider $service): array => [$service->value => AiProviderIcon::of($service)])->all(),
                                self::NOT_NOW => Heroicon::OutlinedNoSymbol,
                            ])
                            ->required()
                            ->live(),
                        $this->keyCallout('transcriber'),
                        $this->serviceKeyField('transcription_api_key', 'transcriber', 'getSpeechKey'),
                    ]),
                Section::make('Voice conversations')
                    ->description('Visitors can talk with an agent in a live call, in the hosted chat and the chat widget. The agent listens, answers out loud and uses its tools. This uses the OpenAI Realtime API, so it needs an OpenAI key, also when your agents answer with another provider.')
                    ->icon(Heroicon::OutlinedPhone)
                    ->schema([
                        CardRadio::make('voice')
                            ->hiddenLabel()
                            ->options([
                                ...collect($voice)->mapWithKeys(fn (AiProvider $service): array => [$service->value => "{$service->label()} Realtime"])->all(),
                                self::NOT_NOW => 'Disable voice conversations',
                            ])
                            ->descriptions([
                                ...collect($voice)->mapWithKeys(fn (AiProvider $service): array => [$service->value => 'Each agent still turns calls on in its own setup. A call costs much more than a typed chat.'])->all(),
                                self::NOT_NOW => 'Visitors chat by typing. The call button is hidden. You can turn calls on later in Settings → AI models.',
                            ])
                            ->icons([
                                ...collect($voice)->mapWithKeys(fn (AiProvider $service): array => [$service->value => AiProviderIcon::of($service)])->all(),
                                self::NOT_NOW => Heroicon::OutlinedNoSymbol,
                            ])
                            ->required()
                            ->live(),
                        $this->keyCallout('voice'),
                        $this->serviceKeyField('voice_api_key', 'voice', 'getVoiceKey'),
                    ]),
            ])
            ->afterValidation(fn () => $this->showingErrors(function (): void {
                $this->checkServiceKey('transcriber', 'transcription_api_key');
                $this->checkServiceKey('voice', 'voice_api_key');
            }));
    }

    private function storageStep(): Step
    {
        return Step::make('Storage')
            ->description('Where files are kept')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->schema([
                Callout::make('We recommend S3-compatible storage')
                    ->description('Files on this server are lost if its disk breaks, and other servers cannot reach them. A bucket keeps them safe and lets the app grow to more servers.')
                    ->icon(Heroicon::OutlinedLightBulb)
                    ->info()
                    ->visible(fn (Get $get): bool => $get('storage_driver') !== 's3' || $get('public_files') !== 'bucket' || $get('backup_files') !== 'bucket'),
                Section::make('Private files')
                    ->description('Knowledge files, chat attachments, and generated images and videos.')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->columns(2)
                    ->schema([
                        CardRadio::make('storage_driver')
                            ->hiddenLabel()
                            ->options(['local' => 'This server', 's3' => 'S3-compatible private bucket'])
                            ->descriptions([
                                'local' => 'Private files are stored on this server.',
                                's3' => 'AWS S3, Cloudflare R2, MinIO and others.',
                            ])
                            ->icons([
                                'local' => Heroicon::OutlinedServer,
                                's3' => asset('images/logos/services/aws.svg'),
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                if ($get('storage_driver') !== 's3' && $get('backup_files') === 'private') {
                                    $set('backup_files', 'local');
                                }
                            })
                            ->columnSpanFull(),
                        ...$this->bucketFields('private', 'Private bucket', 'For example chatvia-files.'),
                    ]),
                Section::make('Public images')
                    ->description('Profile photos and agent logos. A bucket for them must be public.')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->columns(2)
                    ->schema([
                        CardRadio::make('public_files')
                            ->hiddenLabel()
                            ->options(['local' => 'This server', 'bucket' => 'S3-compatible public bucket'])
                            ->descriptions([
                                'local' => 'Public images are stored on this server.',
                                'bucket' => 'A bucket anyone can read.',
                            ])
                            ->icons(['local' => Heroicon::OutlinedServer, 'bucket' => asset('images/logos/services/aws.svg')])
                            ->default('local')
                            ->live()
                            ->columnSpanFull(),
                        ...$this->bucketFields('public', 'Public bucket', 'Turn on public read access for this bucket.'),
                    ]),
                Section::make('Backups')
                    ->description('Backups of the database and Redis. A bucket for them must be private.')
                    ->icon(Heroicon::OutlinedCircleStack)
                    ->columns(2)
                    ->schema([
                        CardRadio::make('backup_files')
                            ->hiddenLabel()
                            ->options(['local' => 'This server', 'bucket' => 'S3-compatible private bucket', 'private' => 'Same as the private bucket'])
                            ->descriptions(fn (Get $get): array => [
                                'local' => 'Backups are stored on this server.',
                                'bucket' => 'Safe if this server breaks.',
                                'private' => $get('storage_driver') === 's3' ? 'Uses the bucket and keys of the private files.' : 'Keep private files in a bucket first.',
                            ])
                            ->icons(['local' => Heroicon::OutlinedServer, 'bucket' => asset('images/logos/services/aws.svg'), 'private' => Heroicon::OutlinedDocumentDuplicate])
                            ->disableOptionWhen(fn (string $value, Get $get): bool => $value === 'private' && $get('storage_driver') !== 's3')
                            ->default('local')
                            ->live()
                            ->afterStateUpdated($this->copyPrivateBucket(...))
                            ->columnSpanFull(),
                        ...$this->bucketFields('backup', 'Backups bucket', 'For example chatvia-backups.'),
                    ]),
            ])
            ->afterValidation(fn () => $this->showingErrors($this->checkStorage(...)));
    }

    /**
     * One bucket's name and connection. Each bucket has its own, so it may
     * sit with another service or account; the public one also asks for the
     * URL that serves its files.
     *
     * @return list<Component>
     */
    private function bucketFields(string $bucket, string $label, string $help): array
    {
        $used = fn (Get $get): bool => $this->usesBucket($get, $bucket);
        $copied = fn (Get $get): bool => $bucket === 'backup' && $get('backup_files') === 'private';
        $shown = fn (Get $get): bool => $used($get) || $copied($get);

        // The backups bucket follows the private one while the backups go
        // there: its fields show the private bucket's and are locked.
        $linked = fn (Field $field): Field => match ($bucket) {
            'private' => $field->live(onBlur: $field instanceof TextInput)->afterStateUpdated($this->copyPrivateBucket(...)),
            'backup' => $field->disabled($copied),
            default => $field,
        };

        return Arr::map([
            TextInput::make("s3_{$bucket}_bucket")
                ->label($label)
                ->helperText($help)
                ->when($bucket === 'public', fn (TextInput $input): TextInput => $input
                    ->different('s3_private_bucket')
                    ->different('s3_backup_bucket')
                    ->validationMessages(['different' => 'Use a bucket of its own: anyone can read the files in the public bucket.']))
                ->required($used)
                ->visible($shown)
                ->columnSpan($bucket === 'public' ? 1 : 'full'),
            ...($bucket === 'public' ? [
                TextInput::make('s3_public_url')
                    ->label('Public URL')
                    ->url()
                    ->helperText('The address that serves its files, for example https://pub-1234.r2.dev or your CDN.')
                    ->required($used)
                    ->visible($shown),
            ] : []),
            TextInput::make("s3_{$bucket}_endpoint")
                ->label('Endpoint')
                ->url()
                ->helperText('For example https://s3.fr-par.scw.cloud or http://minio:9000. Leave empty for AWS S3.')
                ->visible($shown),
            TextInput::make("s3_{$bucket}_region")
                ->label('Region')
                ->helperText('For example fr-par, eu-west-1, or auto for Cloudflare R2.')
                ->required($used)
                ->visible($shown),
            TextInput::make("s3_{$bucket}_key")
                ->label('Access key ID')
                ->required($used)
                ->visible($shown),
            TextInput::make("s3_{$bucket}_secret")
                ->label('Secret access key')
                ->password()
                ->revealable()
                ->autocomplete('new-password')
                ->required(fn (Get $get): bool => $used($get) && blank(config("filesystems.buckets.{$bucket}.secret")))
                ->visible($shown),
            Toggle::make("s3_{$bucket}_path_style")
                ->label('Use path-style addresses')
                ->helperText('MinIO and some other servers need it.')
                ->visible($shown)
                ->columnSpanFull(),
        ], $linked);
    }

    /**
     * Fill the backups bucket's fields with the private bucket's while the
     * backups go to the private bucket, so the locked fields show what is
     * used. Saving uses the private bucket itself ({@see storageData()}).
     */
    private function copyPrivateBucket(Get $get, Set $set): void
    {
        if ($get('backup_files') !== 'private') {
            return;
        }

        foreach (['bucket', 'endpoint', 'region', 'key', 'secret', 'path_style'] as $field) {
            $set("s3_backup_{$field}", $get("s3_private_{$field}"));
        }
    }

    /** Whether the form keeps files in this bucket of S3-compatible storage. */
    private function usesBucket(Get $get, string $bucket): bool
    {
        return match ($bucket) {
            'public' => $get('public_files') === 'bucket',
            'backup' => $get('backup_files') === 'bucket',
            default => $get('storage_driver') === 's3',
        };
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
                                ->mapWithKeys(fn (MailProvider $provider): array => [$provider->value => $provider === MailProvider::Log ? 'Disable email' : $provider->label()])
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

    private function observabilityStep(): Step
    {
        return Step::make('Observability')
            ->description('A log of every AI call')
            ->icon(Heroicon::OutlinedEye)
            ->schema([
                Section::make('Spectra')
                    ->description('Spectra keeps a log of every call ChatVia.ai makes to an AI service: which model answered, how many tokens it used, what it cost and how long it took. It helps you see what your agents cost, and find out why an answer was slow or wrong. The log stays on this server, in a database of its own, and only admins can open it.')
                    ->icon(Heroicon::OutlinedCpuChip)
                    ->schema([
                        CardRadio::make('observability')
                            ->hiddenLabel()
                            ->options([self::SPECTRA => 'Record AI calls with Spectra', self::NOT_NOW => 'Disable Spectra'])
                            ->descriptions([
                                self::SPECTRA => 'Recommended. It also saves the prompts and replies, so you can read a whole call. Choose what it saves, and for how many days, in Settings → Observability.',
                                self::NOT_NOW => 'No AI call is logged, so Spectra shows no costs or times. You can turn it on later in Settings → Observability.',
                            ])
                            ->icons([
                                self::SPECTRA => asset('images/logos/tools/spectra.svg'),
                                self::NOT_NOW => Heroicon::OutlinedNoSymbol,
                            ])
                            ->required(),
                        Callout::make('Spectra is left out of this install')
                            ->description('The server\'s .env file has SPECTRA_ENABLED=false, so Spectra cannot record, whatever you choose here. Remove that line to use it.')
                            ->icon(Heroicon::OutlinedExclamationTriangle)
                            ->warning()
                            ->visible(fn (): bool => ! app(ToolRecording::class)->spectraInstalled()),
                    ]),
            ]);
    }

    private function appStep(): Step
    {
        return Step::make('App')
            ->description('Its name and license')
            ->icon(Heroicon::OutlinedTag)
            ->schema([
                Section::make('App name')
                    ->description('The name people see in browser tabs, the dashboard, the admin panel and emails. You can change it later in Settings → Platform.')
                    ->icon(Heroicon::OutlinedTag)
                    ->schema([
                        TextInput::make('app_name')
                            ->label('App name')
                            ->required()
                            ->maxLength(60)
                            ->live(onBlur: true)
                            // The sender name of emails follows until it is changed by hand.
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?string $old): void {
                                if ($get('from_name') === $old) {
                                    $set('from_name', $state);
                                }
                            }),
                    ]),
                Section::make('License (optional)')
                    ->description('ChatVia.ai is free to use, with a few limits. A license key lifts them and supports its development.')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->schema([
                        $this->licenseCallout(),
                        TextInput::make('license_key')
                            ->label('License key')
                            ->password()
                            ->revealable()
                            ->autocomplete('off')
                            ->placeholder('cvl1.…')
                            ->helperText(fn (): string => $this->runsOnLocalAddress()
                                ? 'Paste the whole key from your email. Next activates it at chatvia.ai for this address, which only this computer opens: remove it on the License page before ChatVia.ai moves to its real address.'
                                : 'Paste the whole key from your email. Next activates it for this site at chatvia.ai. You can also add it later on the License page.')
                            ->visible(fn (): bool => $this->licenseStatus() !== LicenseStatus::Valid),
                    ]),
            ])
            ->afterValidation(fn () => $this->showingErrors(fn () => $this->activateLicense()));
    }

    private function finalStep(): Step
    {
        return Step::make('Final')
            ->description('Finish and sign in')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->schema([
                View::make('admin.setup.complete')
                    ->viewData(['loginUrl' => route('login')]),
            ]);
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
                    $embedding = $this->suggestedEmbedding(AiProvider::tryFrom($state ?? ''));
                    $set('embedding_provider', $embedding?->value);
                    $set('embedding_model', $embedding instanceof EmbeddingProvider ? config($embedding->modelConfig()) : null);
                    $set('document_reader', $this->suggestedReader(AiProvider::tryFrom($state ?? '')));
                    $set('reranker', $this->suggestedReranker(AiProvider::tryFrom($state ?? '')));
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
            TextInput::make('chat_endpoint')
                ->label('Endpoint')
                ->url()
                ->helperText(fn (Get $get): ?string => AiProvider::tryFrom((string) $get('provider'))?->baseUrlHelp())
                ->required(fn (Get $get): bool => blank(config(AiProvider::tryFrom((string) $get('provider'))?->baseUrlConfig() ?? '')))
                ->placeholder(fn (Get $get): ?string => config(AiProvider::tryFrom((string) $get('provider'))?->baseUrlConfig() ?? '') ?: null)
                ->visible(fn (Get $get): bool => (bool) AiProvider::tryFrom((string) $get('provider'))?->needsBaseUrl())
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
                ->afterStateUpdated(fn (Set $set, ?string $state): mixed => $set('embedding_model', ($service = EmbeddingProvider::tryFrom($state ?? '')) instanceof EmbeddingProvider ? config($service->modelConfig()) : null)),
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
                ->options(fn (): array => ModelLogo::labels(app(OpenRouterEmbeddingModels::class)->options($this->storedDimensions()), LlmProviderKind::OpenRouter))
                ->allowHtml()
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
                    self::NOT_NOW => 'Disable reranker',
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
                ->options(fn (): array => ModelLogo::labels(app(RerankModelCatalog::class)->openRouterOptions(), LlmProviderKind::OpenRouter))
                ->allowHtml()
                ->searchable()
                ->required($chosen(AiProvider::OpenRouter->value))
                ->helperText('The strongest first. The list comes from OpenRouter, so new rerankers show up here.')
                ->visible($chosen(AiProvider::OpenRouter->value)),
            Select::make('cohere_rerank_model')
                ->label('Model')
                ->options(fn (): array => collect(app(RerankModelCatalog::class)->cohereOptions())->map(fn (string $name): string => ModelLogo::forLab($name, 'cohere'))->all())
                ->allowHtml()
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
            ViewField::make('chat_models')
                ->label('Models agents can use')
                ->view('admin.setup.chat-models')
                ->viewData(fn (Get $get): array => [
                    'chosen' => collect((array) $get('chat_models'))
                        ->mapWithKeys(fn (string $id): array => [$id => $this->modelName($id)])
                        ->all(),
                    'withoutTools' => $this->modelsWithoutTools,
                    'logos' => collect((array) $get('chat_models'))
                        ->mapWithKeys(fn (string $id): array => [$id => ModelLogo::url($this->chatKind((array) $this->data), $id)])
                        ->all(),
                ])
                ->required(fn (): bool => $this->models !== [])
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (collect((array) $value)->contains(fn (mixed $id): bool => ! array_key_exists((string) $id, $this->models))) {
                        $fail('Pick the models from the provider\'s list.');
                    }
                })
                ->validationMessages(['required' => 'Pick at least one model for your agents.'])
                ->helperText(fn (): string => count($this->models).' chat models to choose from. The suggested ones call tools well.')
                ->visible(fn (): bool => $this->models !== []),
            Callout::make(fn (Get $get): string => $this->noToolsHeading($get))
                ->description(fn (Get $get): string => count($this->chosenWithoutTools($get)) === 1
                    ? 'Tools will not work for agents that use it: they answer without them. You can still keep it.'
                    : 'Tools will not work for agents that use them: they answer without them. You can still keep them.')
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->warning()
                ->visible(fn (Get $get): bool => $this->chosenWithoutTools($get) !== []),
            Select::make('default_model')
                ->label('Default for new agents')
                ->options(fn (Get $get): array => $this->modelOptions((array) $get('chat_models')))
                ->allowHtml()
                ->native(false)
                ->selectablePlaceholder(false)
                ->required(fn (): bool => $this->models !== [])
                ->visible(fn (Get $get): bool => count((array) $get('chat_models')) > 1),
            Select::make('quick_task_model')
                ->label('Model for quick tasks')
                ->options(fn (): array => $this->modelOptions())
                ->allowHtml()
                ->in(fn (): array => array_keys($this->models))
                ->searchable()
                ->placeholder('Same as the chat model')
                ->helperText(fn (): Htmlable => $this->quickTasksHelp())
                ->visible(fn (): bool => $this->models !== []),
            Toggle::make('describe_images')
                ->label('Describe images')
                ->live()
                ->helperText(fn (Get $get): string => $this->visionHelp($get))
                ->visible(fn (): bool => $this->models !== []),
            Select::make('vision_model')
                ->label('Model for image descriptions')
                ->options(fn (): array => $this->modelOptions($this->imageModels()))
                ->allowHtml()
                ->in(fn (): array => $this->imageModels())
                ->searchable()
                ->required()
                ->helperText(fn (): string => collect($this->modelsReadingImages)->containsStrict(true) ? 'Only the models that read images are listed.' : 'A model that reads images.')
                ->hintAction($this->testAction(self::TEST_IMAGES))
                ->visible(fn (Get $get): bool => $this->models !== [] && (bool) $get('describe_images')),
            Toggle::make('chat_files')
                ->label('Chat file uploads')
                ->live()
                ->helperText(fn (Get $get): string => $this->filesHelp($get))
                ->visible(fn (): bool => $this->models !== []),
            Select::make('files_model')
                ->label('Model for chat file uploads')
                ->options(fn (): array => $this->modelOptions($this->fileModels()))
                ->allowHtml()
                ->in(fn (): array => $this->fileModels())
                ->searchable()
                ->required()
                ->helperText('Only the models that read both PDF files and images are listed.')
                ->hintAction($this->testAction(self::TEST_FILES))
                ->visible(fn (Get $get): bool => $this->models !== [] && (bool) $get('chat_files')),
            Callout::make('Image descriptions are off')
                ->description('Pictures inside PDF files and other documents are not described, so search cannot find what they show. The text of your documents is still read. Add a model that reads images later in Settings → AI models, then turn on Settings → AI models → Documents → Describe images.')
                ->icon(Heroicon::OutlinedPhoto)
                ->info()
                ->visible(fn (): bool => $this->models === []),
            TextInput::make('chat_model_id')
                ->label('Model')
                ->placeholder(fn (Get $get): string => $this->modelNameHelp($get) !== null ? 'For example gpt-4.1' : 'For example llama3.1:8b')
                ->helperText(fn (Get $get): string => ($this->modelNameHelp($get) ?? 'The model\'s name exactly as your provider writes it.').' You can add more later in Settings → AI models.')
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
            $component->hintAction(Action::make("getMailKey_{$provider->value}")
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
     * What to do to get pg_textsearch: install it, load it when PostgreSQL
     * starts, or turn it on here. Once it is on, the section is hidden.
     */
    private function keywordSearchCallout(): Callout
    {
        $why = 'pg_textsearch ranks the words with BM25, the method search engines use: rare words such as product names, codes and error messages count more than common ones, and a long document does not win just for being long. Without it, ChatVia.ai uses the built-in search of PostgreSQL. That works, but ranks less well, most of all without a reranker.';
        $load = "Add it to <code>shared_preload_libraries</code> in <code>postgresql.conf</code> (<code>shared_preload_libraries = 'pg_textsearch'</code>), restart PostgreSQL, then press <strong>Check again</strong>.";

        return Callout::make(fn (): string => match ($this->pgTextsearchState()) {
            PgTextsearchState::Active, PgTextsearchState::SwitchedOff => 'pg_textsearch is installed, but not used',
            PgTextsearchState::ReadyToEnable => 'Turn on pg_textsearch',
            PgTextsearchState::NotPreloaded => 'Load pg_textsearch when PostgreSQL starts',
            PgTextsearchState::NotInstalled => 'Install pg_textsearch (recommended)',
        })
            ->description(fn (): HtmlString => new HtmlString(match ($this->pgTextsearchState()) {
                PgTextsearchState::Active, PgTextsearchState::SwitchedOff => 'The <code>SEARCH_LEXICAL_DRIVER</code> setting in <code>.env</code> keeps the built-in search. Remove it, then run <code>php artisan chatvia:search:lexical-indexes</code>. '.e($why),
                PgTextsearchState::ReadyToEnable => e($why).' The database has it loaded: turn it on here. This also rebuilds the search indexes.',
                PgTextsearchState::NotPreloaded => e($why)." It is installed on the database server, but PostgreSQL does not load it. {$load}",
                PgTextsearchState::NotInstalled => e($why).' Install it from its <a href="https://github.com/timescale/pg_textsearch/releases" target="_blank" rel="noopener" class="underline">releases page</a> (the Docker setup and <code>install.sh</code> include it). '."{$load} A managed cloud database often cannot load it: then go on, the built-in search works.",
            }))
            ->icon(Heroicon::OutlinedLightBulb)
            ->status(fn (): string => $this->pgTextsearchState() === PgTextsearchState::SwitchedOff ? 'info' : 'warning')
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

    /**
     * pg_partman is missing: analytics needs it, so this warns and links the
     * docs that say why. Setup still goes on without it.
     */
    private function analyticsCallout(): Callout
    {
        return Callout::make('pg_partman is not installed')
            ->description(new HtmlString(
                'Analytics needs pg_partman to split its message log by month. Without it, analytics will not work as it should. '
                .'<a href="'.e(config('chatvia.docs_url').'/guide/how-it-works/background#analytics').'" target="_blank" rel="noopener" class="underline">Read more</a>'
            ))
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->status('warning');
    }

    private function hasPartman(): bool
    {
        return $this->partman ??= app(AnalyticsDatabase::class)->partmanVersion() !== null;
    }

    /**
     * Where the license stands: what a key adds while there is none, with a
     * link to buy one, or the saved key's type and licensee.
     */
    private function licenseCallout(): Callout
    {
        $limits = 'A free install has one workspace, with no limits on agents, teams or replies. '
            .'It has no API, voice conversations, live chat, capabilities, forms, or sentiment, tone, topics and sources analytics, and the "Powered by ChatVia.ai" credit shows under every chat.';

        return Callout::make(fn (): string => match ($this->licenseStatus()) {
            LicenseStatus::Valid => "{$this->licenseType()} license, licensed to ".$this->license()->read($this->savedLicenseKey())?->licensee,
            LicenseStatus::Missing => 'Get the most out of ChatVia.ai',
            default => 'The saved license is not active here',
        })
            ->description(fn (): string => match (true) {
                $this->licenseStatus() === LicenseStatus::Valid => 'Every feature is on. You can hide the credit on the License page.',
                $this->runsOnLocalAddress() => "{$limits} A key works for one domain at a time, so it is best added once ChatVia.ai runs at its real address.",
                default => $limits.' A license key turns these on and lets you hide the credit. A Pro license costs $199 a year.',
            })
            ->icon(fn (): Heroicon => $this->licenseStatus() === LicenseStatus::Valid ? Heroicon::OutlinedCheckBadge : Heroicon::OutlinedSparkles)
            ->status(fn (): string => $this->licenseStatus() === LicenseStatus::Valid ? 'success' : 'info')
            ->actions([
                Action::make('buyLicense')
                    ->label('Buy a license')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->iconPosition('after')
                    ->url(fn (): ?string => config('chatvia.license.buy_url'))
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => filled(config('chatvia.license.buy_url')) && $this->licenseStatus() !== LicenseStatus::Valid),
            ]);
    }

    /**
     * The app step's license check: a typed key is activated for this
     * domain at chatvia.ai and saved at once, so it works whether the setup
     * is finished or skipped. The field is emptied once it is, so going back
     * and on does not activate it again.
     */
    private function activateLicense(): void
    {
        $key = Str::of((string) ($this->data['license_key'] ?? ''))->replaceMatches('/\s+/', '')->toString();

        if ($key === '') {
            return;
        }

        $activation = app(ActivateLicenseAction::class)->execute($key);

        if ($activation->key === null) {
            throw ValidationException::withMessages(['data.license_key' => (string) $activation->message]);
        }

        $this->data['license_key'] = null;

        Notification::make()->success()->title('License activated')->body('Thank you for supporting ChatVia.ai.')->send();
    }

    /** "Enterprise" or "Pro", by what the saved key unlocks. */
    private function licenseType(): string
    {
        return $this->license()->allows($this->savedLicenseKey(), LicenseFeature::Enterprise) ? 'Enterprise' : 'Pro';
    }

    private function licenseStatus(): LicenseStatus
    {
        return $this->license()->status($this->savedLicenseKey());
    }

    private function savedLicenseKey(): ?string
    {
        $key = app(BrandSettings::class)->license_key;

        return filled($key) ? $key : null;
    }

    /** Whether only this computer opens the install's address: a key activated for it would be tied to it. */
    private function runsOnLocalAddress(): bool
    {
        $host = Str::trim($this->license()->host(), '[]');

        return collect(['', 'localhost', '::1'])->containsStrict($host)
            || Str::endsWith($host, '.localhost') || Str::startsWith($host, '127.');
    }

    private function license(): CreditLicense
    {
        return app(CreditLicense::class);
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
            <x-filament::link tag="button" type="button" size="sm" wire:click="mountAction('quickTasks', { task: 'titles' })">titles</x-filament::link> and
            <x-filament::link tag="button" type="button" size="sm" wire:click="mountAction('quickTasks')">other small jobs</x-filament::link> run around each reply.
            A small, fast model keeps replies quick and cheap.
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
            ->modalContent(fn (array $arguments): ?ViewContract => $task($arguments) instanceof LlmTask ? null : view('admin.setup.quick-tasks', [
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
     * Changes the chat models agents can use: every listed model with a box
     * to tick, the chosen ones first, then the suggested ones, and a search
     * over names and ids. Done keeps the choice (Cancel drops it); the
     * default for new agents stays one of the chosen.
     */
    public function manageChatModelsAction(): Action
    {
        return Action::make('manageChatModels')
            ->label('Manage models')
            ->modalHeading('Models agents can use')
            ->modalDescription('Tick the models your agents can pick from. Each agent uses one of them.')
            ->modalWidth(Width::TwoExtraLarge)
            ->fillForm(fn (): array => ['models' => (array) ($this->data['chat_models'] ?? [])])
            ->schema([
                ModelPicker::make('models')
                    ->options(fn (): array => $this->modelChoices())
                    ->descriptions(fn (): array => $this->modelChoiceDescriptions())
                    ->required()
                    ->validationMessages(['required' => 'Pick at least one model for your agents.']),
            ])
            ->modalSubmitActionLabel('Done')
            ->action(function (array $data): void {
                $ticked = collect((array) ($data['models'] ?? []))
                    ->map(fn (mixed $id): string => (string) $id)
                    ->filter(fn (string $id): bool => array_key_exists($id, $this->models));

                // The models chosen before keep their places; new ones follow.
                $chosen = collect((array) ($this->data['chat_models'] ?? []))->intersect($ticked)->merge($ticked)->unique()->values()->all();

                $this->data['chat_models'] = $chosen;

                if (! collect($chosen)->containsStrict($this->data['default_model'] ?? null)) {
                    $this->data['default_model'] = $chosen[0] ?? null;
                }
            });
    }

    /**
     * "Test" on a section of the models step (and on the models for image
     * descriptions and chat file uploads): the test window with the
     * section's rows ({@see testWindow()}), shown once there is a model to
     * test.
     */
    private function testAction(string $section): Action
    {
        return $this->testWindow(Action::make('test'.Str::studly($section)), [$section])
            ->label('Test')
            ->link()
            ->icon(Heroicon::OutlinedPlayCircle)
            ->modalHeading(match ($section) {
                self::TEST_CHAT => 'Testing the chat models',
                self::TEST_EMBEDDINGS => 'Testing the embedding model',
                self::TEST_IMAGES => 'Testing the model for image descriptions',
                self::TEST_FILES => 'Testing the model for chat file uploads',
                default => 'Testing the reranker',
            })
            ->modalDescription(match ($section) {
                self::TEST_IMAGES => 'It is shown this picture of a cat and asked to describe it, with the key you entered.',
                self::TEST_FILES => 'It is sent a small PDF file of one page and asked to summarize it, with the key you entered.',
                default => 'Each one is asked to answer now, with the key you entered.',
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->visible(fn (): bool => $this->testRows($section) !== []);
    }

    /**
     * Next on the models step: the test window with each section of the
     * step under its title (chat models, embeddings, reranker), and
     * Continue. Continue waits for the test, and stays off while something
     * did not answer: the admin changes or removes it and presses Next
     * again. A busy chat model does not stop it. Continuing moves the
     * wizard on.
     */
    public function testModelsAction(): Action
    {
        return $this->testWindow(Action::make('testModels'), self::STEP_TESTS, onFailure: 'To go on, change or remove what did not answer, then press Next again.')
            ->modalHeading('Testing the models')
            ->modalDescription('Each one is asked to answer now, with the keys you entered.')
            ->modalSubmitActionLabel('Continue')
            ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes([
                // Off until the window says each one answered or was busy.
                'x-data' => '{ ready: false }',
                'x-on:model-tests-done.window' => 'ready = ! $event.detail.failed',
                'x-bind:disabled' => '! ready',
            ]))
            ->modalCancelActionLabel('Back to models')
            ->action(function (Action $action): void {
                if (! $this->modelsAnswered()) {
                    $action->halt();
                }

                $this->leaveStep(self::MODELS_STEP);
            });
    }

    /**
     * The test window: it asks each model of the sections given to answer
     * now, with the key typed, a few at once as it opens. Each section
     * is one short card: how many are done, the model being asked, and a
     * row of marks that turn into ticks, warnings when the provider is
     * busy, or crosses; the reasons are listed below ({@see testModel()}).
     * A section with nothing to test is left out.
     *
     * @param  list<string>  $sections
     * @param  string|null  $onFailure  What to do when a model did not answer, shown under the summary.
     */
    private function testWindow(Action $action, array $sections, ?string $onFailure = null): Action
    {
        return $action
            ->modalWidth(Width::Large)
            ->modalContent(fn (): ViewContract => view('admin.setup.model-tests', [
                'sections' => collect($sections)
                    ->map(fn (string $section): array => [
                        'section' => $section,
                        'title' => $this->sectionTitle($section),
                        'rows' => $this->testRows($section),
                    ])
                    ->reject(fn (array $section): bool => $section['rows'] === [])
                    ->values()
                    ->all(),
                'image' => collect($sections)->containsStrict(self::TEST_IMAGES) ? 'data:image/png;base64,'.base64_encode(File::get(resource_path('admin/samples/setup/test-cat.png'))) : null,
                'onFailure' => $onFailure,
            ]));
    }

    /** The title of a section of the models step, on the step and in the test window. */
    private function sectionTitle(string $section): string
    {
        return match ($section) {
            self::TEST_CHAT => 'Chat models',
            self::TEST_EMBEDDINGS => 'Embeddings',
            self::TEST_RERANKER => 'Reranker',
            self::TEST_IMAGES => 'Model for image descriptions',
            default => 'Model for chat file uploads',
        };
    }

    /**
     * What a section's test checks, each by a key, its label (HTML, with the
     * model's logo) and its name as plain text: the chosen chat models, the
     * embedding model, the reranker, or the model for image descriptions;
     * none when nothing is chosen.
     *
     * @return list<array{key: string, label: string, name: string}>
     */
    private function testRows(string $section): array
    {
        $state = (array) $this->data;

        $model = fn (string $id): array => [
            'key' => $id,
            'label' => ModelLogo::label($this->modelName($id), $this->chatKind($state), $id),
            'name' => $this->modelName($id),
        ];
        $service = fn (string $key, string $name): array => ['key' => $key, 'label' => e($name), 'name' => $name];

        return match ($section) {
            self::TEST_CHAT => array_map(fn (RemoteModelData $chosen): array => $model($chosen->id), $this->chosenModels($state)),
            self::TEST_IMAGES => (bool) ($state['describe_images'] ?? false) && filled($state['vision_model'] ?? null) && $this->models !== []
                ? [$model((string) $state['vision_model'])]
                : [],
            self::TEST_FILES => (bool) ($state['chat_files'] ?? false) && filled($state['files_model'] ?? null) && $this->models !== []
                ? [$model((string) $state['files_model'])]
                : [],
            self::TEST_EMBEDDINGS => ($provider = $this->embeddingProvider($state)) instanceof EmbeddingProvider
                ? [$service(self::TEST_EMBEDDINGS, $provider->label().(filled($state['embedding_model'] ?? null) ? " · {$state['embedding_model']}" : ''))]
                : [],
            default => ($reranker = $this->rerankerData($state))->isOn()
                ? [$service(self::TEST_RERANKER, (AiProvider::tryFrom($reranker->driver)?->label() ?? 'Your reranker server').(filled($reranker->model) ? " · {$reranker->model}" : ''))]
                : [],
        };
    }

    /**
     * Checks one row of a section's test, called by the test window as it
     * opens; nothing on the page is drawn again. Only what the section
     * tests is asked: a chosen chat model, the model for image descriptions,
     * the model for chat file uploads. The window runs a few of these at
     * once, each its own request, and the last answer back would overwrite
     * the page state the others changed: so how each one answered is kept
     * in the cache instead ({@see rememberCheck()}), and Next goes on once
     * each one answered ({@see modelsAnswered()}).
     *
     * @return array{state: string, message: string|null}
     */
    #[Async]
    #[Renderless]
    public function testModel(string $section, string $key): array
    {
        $state = $this->form->getRawState();
        $testable = collect(array_column($this->testRows($section), 'key'))->containsStrict($key);

        $check = match (true) {
            ! $testable => ModelCheckData::failed('This is not one of the models to test.'),
            $section === self::TEST_CHAT => app(ChatModelCheck::class)->check($this->draftProvider($state), $key),
            $section === self::TEST_IMAGES => app(ChatModelCheck::class)->checkImage($this->draftProvider($state), $key),
            $section === self::TEST_FILES => app(ChatModelCheck::class)->checkFile($this->draftProvider($state), $key),
            $section === self::TEST_EMBEDDINGS => ($failure = $this->embeddingFailure($state)) !== null ? ModelCheckData::failed($failure[1]) : ModelCheckData::passed(),
            default => ($failure = $this->rerankerFailure($state)) !== null ? ModelCheckData::failed($failure[1]) : ModelCheckData::passed(),
        };

        if ($testable && $section === self::TEST_CHAT) {
            $this->rememberCheck("chat:{$key}", $this->fingerprint($state), $check->state);
        }

        return ['state' => $check->state, 'message' => $check->message];
    }

    /**
     * Asked when Next leaves the ingestion step on "Disable document reader": without a
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
                $this->leaveStep(self::INGESTION_STEP);
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
                Arr::join(array_map(fn (Service $service): string => $service->label(), $this->servicesDown()), ', '),
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
                Arr::join(app(SetupRequirements::class)->missing(), ', ') ?: 'nothing',
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

        if (collect([ModelListStatus::KeyRefused->value, ModelListStatus::Unreachable->value])->containsStrict($this->listStatus)) {
            $field = match (true) {
                $this->listStatus !== ModelListStatus::Unreachable->value => 'data.api_key',
                ! $this->account($state) instanceof AiProvider => 'data.server_url',
                $this->account($state)?->needsBaseUrl() => 'data.chat_endpoint',
                default => 'data.api_key',
            };

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
        $this->checkChatModelsChosen();

        if (($failure = $this->embeddingFailure($this->form->getRawState())) !== null) {
            throw ValidationException::withMessages([$failure[0] => $failure[1]]);
        }
    }

    /** At least one chat model is chosen. */
    private function checkChatModelsChosen(): void
    {
        if ($this->chosenModels($this->form->getRawState()) === []) {
            throw ValidationException::withMessages([
                $this->models !== [] ? 'data.chat_models' : 'data.chat_model_id' => 'Pick at least one model for your agents.',
            ]);
        }
    }

    /**
     * Embeds a test text with the embedding service, model and key chosen;
     * null when it works (or there is no service to check), else the field
     * to mark and why. A pass is kept, so it is not checked again.
     *
     * @param  array<string, mixed>  $state
     * @return array{string, string}|null
     */
    private function embeddingFailure(array $state): ?array
    {
        $provider = $this->embeddingProvider($state);

        if (! $provider instanceof EmbeddingProvider) {
            return null;
        }

        $keyField = $this->earlierKeyField('embedding_provider', $state) ?? 'embedding_api_key';
        $key = $state[$keyField] ?? null;
        $model = filled($state['embedding_model'] ?? null) ? (string) $state['embedding_model'] : null;
        $fingerprint = $this->embeddingFingerprint($state);

        if ($this->rememberedCheck(self::TEST_EMBEDDINGS, $fingerprint) === ModelCheckData::PASSED) {
            return null;
        }

        $result = app(EmbeddingCheck::class)->check($provider, $model, filled($key) ? (string) $key : null, $this->storedDimensions());

        if (! $result->ok()) {
            $field = $result->status === EmbeddingCheckStatus::KeyRefused ? "data.{$keyField}" : 'data.embedding_model';

            return [$field, $result->message($provider->label(), $this->storedDimensions())];
        }

        $this->rememberCheck(self::TEST_EMBEDDINGS, $fingerprint, ModelCheckData::PASSED);

        return null;
    }

    /**
     * What the embedding check depends on: the service, model and key chosen.
     *
     * @param  array<string, mixed>  $state
     */
    private function embeddingFingerprint(array $state): string
    {
        $key = $state[$this->earlierKeyField('embedding_provider', $state) ?? 'embedding_api_key'] ?? null;

        return hash('sha256', Arr::join([(string) ($state['embedding_provider'] ?? ''), (string) ($state['embedding_model'] ?? ''), (string) $key], "\n"));
    }

    /**
     * The models step's check of the reranker: it ranks a test question with
     * the model and key chosen ({@see RerankerCheck}). A key typed in an
     * earlier step was checked there, so a refusal then points at the model.
     */
    private function checkReranker(): void
    {
        if (($failure = $this->rerankerFailure($this->form->getRawState())) !== null) {
            throw ValidationException::withMessages([$failure[0] => $failure[1]]);
        }
    }

    /**
     * Ranks a test question with the reranker, model and key chosen; null
     * when it works (or none is chosen), else the field to mark and why. A
     * pass is kept, so it is not checked again.
     *
     * @param  array<string, mixed>  $state
     * @return array{string, string}|null
     */
    private function rerankerFailure(array $state): ?array
    {
        $chosen = $this->rerankerData($state);

        if (! $chosen->isOn()) {
            return null;
        }

        $earlier = $this->earlierKeyField('reranker', $state);
        $keyField = $this->rerankerKeyField($state);
        $reranker = new RerankerSetupData($chosen->driver, $chosen->model, $this->filled($state, $keyField), $chosen->url);
        $fingerprint = $this->rerankerFingerprint($state);

        if ($this->rememberedCheck(self::TEST_RERANKER, $fingerprint) === ModelCheckData::PASSED) {
            return null;
        }

        $check = app(RerankerCheck::class)->check($reranker);

        if (! $check->connected) {
            $field = match (true) {
                $check->keyRefused && $earlier === null && filled($reranker->apiKey) => $keyField,
                $reranker->driver === self::CUSTOM => 'rerank_custom_url',
                default => "{$reranker->driver}_rerank_model",
            };

            return ["data.{$field}", (string) $check->message];
        }

        $this->rememberCheck(self::TEST_RERANKER, $fingerprint, ModelCheckData::PASSED);

        return null;
    }

    /**
     * What the reranker check depends on: the reranker, model, key and
     * address chosen.
     *
     * @param  array<string, mixed>  $state
     */
    private function rerankerFingerprint(array $state): string
    {
        $chosen = $this->rerankerData($state);

        return hash('sha256', Arr::join([$chosen->driver, $chosen->model ?? '', $this->filled($state, $this->rerankerKeyField($state)) ?? '', $chosen->url ?? ''], "\n"));
    }

    /**
     * The field of the reranker's key: a server of your own has its own, a
     * key typed in an earlier step is used again.
     *
     * @param  array<string, mixed>  $state
     */
    private function rerankerKeyField(array $state): string
    {
        return $this->rerankerData($state)->driver === self::CUSTOM ? 'rerank_custom_key' : ($this->earlierKeyField('reranker', $state) ?? 'rerank_api_key');
    }

    /** The ingestion step's check: the reader's key (and Azure's endpoint) work. */
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

    /** Stops Next on "Disable document reader" until the admin agrees ({@see noReaderAction()}). */
    private function confirmNoReader(): void
    {
        if (($this->form->getRawState()['document_reader'] ?? null) !== self::NOT_NOW || $this->noReaderConfirmed) {
            return;
        }

        $this->mountAction('noReader');

        throw new Halt;
    }

    /** Stops Next on the models step until each model answered its test ({@see testModelsAction()}). */
    private function confirmModels(): void
    {
        if ($this->modelsAnswered()) {
            return;
        }

        $this->mountAction('testModels');

        throw new Halt;
    }

    /**
     * Whether each model of the step answered its test with what is typed
     * now: each chosen chat model (or its provider was busy), and the
     * embedding model and the reranker when they are chosen.
     */
    private function modelsAnswered(): bool
    {
        $state = $this->form->getRawState();
        $models = $this->chosenModels($state);

        $chat = $models !== []
            && collect($models)->every(fn (RemoteModelData $model): bool => collect([ModelCheckData::PASSED, ModelCheckData::BUSY])->containsStrict($this->rememberedCheck("chat:{$model->id}", $this->fingerprint($state))));
        $embeddings = ! $this->embeddingProvider($state) instanceof EmbeddingProvider || $this->rememberedCheck(self::TEST_EMBEDDINGS, $this->embeddingFingerprint($state)) === ModelCheckData::PASSED;
        $reranker = ! $this->rerankerData($state)->isOn() || $this->rememberedCheck(self::TEST_RERANKER, $this->rerankerFingerprint($state)) === ModelCheckData::PASSED;

        return $chat && $embeddings && $reranker;
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

    /** The ingestion step's check: Firecrawl accepts the key typed. */
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
     * The check of a section that picks an AI provider's service (speech to
     * text, voice conversations): its key works, the one typed here or in an earlier step,
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

    /**
     * The storage step's check: each bucket of S3-compatible storage writes,
     * reads and deletes a test file, and the public one also serves it at
     * its public URL.
     */
    private function checkStorage(): void
    {
        $state = $this->form->getRawState();
        $storage = $this->storageData($state);
        $check = app(StorageCheck::class);

        // Backups kept in the private bucket need no second check.
        $backup = ($state['backup_files'] ?? null) === 'private' ? null : $storage->backup;

        $errors = collect(['private' => $storage->private, 'public' => $storage->public, 'backup' => $backup])
            ->filter()
            ->mapWithKeys(fn (S3BucketData $bucket, string $name): array => ["data.s3_{$name}_bucket" => $check->check(
                $bucket->secret === null ? S3BucketData::from([...$bucket->toArray(), 'secret' => config("filesystems.buckets.{$name}.secret")]) : $bucket,
            )])
            ->filter();

        if ($errors->isNotEmpty()) {
            throw ValidationException::withMessages($errors->all());
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
        $this->listMessage = $list->status === ModelListStatus::Empty && $adapter->modelNameHelp() !== null
            ? null
            : $list->message($this->providerLabel($state['provider'] ?? null), $adapter->baseUrl($draft));

        Cache::put($this->modelCacheKey(), collect($list->models)->keyBy('id')->map(fn (RemoteModelData $model): array => $model->toArray())->all(), now()->addMinutes(10));

        $this->models = collect($list->models)->mapWithKeys(fn (RemoteModelData $model): array => [
            $model->id => $model->name === $model->id ? $model->id : "{$model->name} ({$model->id})",
        ])->all();

        $catalog = app(ModelsDevCatalog::class);
        $facts = collect($list->models)->mapWithKeys(fn (RemoteModelData $model): array => [$model->id => $catalog->facts($draft->kind, $model->id)]);
        $this->modelsWithoutTools = $facts->filter(fn (?ModelFactsData $fact): bool => $fact?->callsTools === false)->keys()->map(fn (string|int $id): string => (string) $id)->values()->all();
        $this->modelsReadingImages = collect($list->models)
            ->mapWithKeys(fn (RemoteModelData $model): array => [$model->id => $model->features['vision'] ?? $facts[$model->id]?->readsImages])
            ->filter(fn (?bool $reads): bool => $reads !== null)
            ->all();
        $this->modelsReadingFiles = collect($list->models)
            ->filter(fn (RemoteModelData $model): bool => ($model->features['files'] ?? $facts[$model->id]?->readsFiles) === true
                && ($model->features['vision'] ?? $facts[$model->id]?->readsImages) === true)
            ->map(fn (RemoteModelData $model): string => $model->id)
            ->values()
            ->all();

        $presets = app(ModelPresets::class);
        $account = $this->account($state);
        $suggested = array_map(fn (RemoteModelData $model): string => $model->id, $presets->chatModels($account, $list->models));
        $this->suggestedModels = $suggested;

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
            $this->data['describe_images'] = $vision instanceof RemoteModelData;
        }

        $files = $presets->fileModel($account, $list->models);
        $this->listsFileModel = $files instanceof RemoteModelData;

        if (blank($this->data['files_model'] ?? null)) {
            $this->data['files_model'] = $files?->id;
            $this->data['chat_files'] = $files instanceof RemoteModelData;
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
            : array_filter([Str::trim((string) ($state['chat_model_id'] ?? ''))], filled(...));

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
        $id = Str::trim((string) ($state[$field] ?? ''));

        return $id === '' || $this->models === [] ? null : $this->listedModel($id) ?? new RemoteModelData($id, $id);
    }

    /**
     * The help under "Describe images": what it does, and, while it is off,
     * why: the admin turned it off, or no recommended model reads images.
     */
    private function visionHelp(Get $get): string
    {
        $does = 'Describes the pictures inside PDF files and other documents, so search can find what they show. Needs a model that reads images.';

        if ((bool) $get('describe_images')) {
            return $does;
        }

        $why = $this->listsVisionModel
            ? 'Off: pictures are not described.'
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
        $driver = ($state['storage_driver'] ?? 'local') === 's3' ? 's3' : 'local';

        return new StorageSetupData(
            driver: $driver,
            private: $driver === 's3' ? $this->bucketData($state, 'private') : null,
            public: ($state['public_files'] ?? null) === 'bucket' ? $this->bucketData($state, 'public') : null,
            backup: match ($state['backup_files'] ?? null) {
                'bucket' => $this->bucketData($state, 'backup'),
                'private' => $driver === 's3' ? $this->privateBucketForBackups($state) : null,
                default => null,
            },
        );
    }

    /**
     * The private bucket as the backups' bucket. A blank secret keeps the
     * saved one for the private bucket, so the backups get that one.
     *
     * @param  array<string, mixed>  $state
     */
    private function privateBucketForBackups(array $state): S3BucketData
    {
        $bucket = $this->bucketData($state, 'private');

        return $bucket->secret === null
            ? S3BucketData::from([...$bucket->toArray(), 'secret' => config('filesystems.buckets.private.secret')])
            : $bucket;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function bucketData(array $state, string $bucket): S3BucketData
    {
        return new S3BucketData(
            bucket: $this->filled($state, "s3_{$bucket}_bucket"),
            endpoint: $this->filled($state, "s3_{$bucket}_endpoint"),
            region: $this->filled($state, "s3_{$bucket}_region"),
            key: $this->filled($state, "s3_{$bucket}_key"),
            secret: $this->filled($state, "s3_{$bucket}_secret"),
            pathStyle: (bool) ($state["s3_{$bucket}_path_style"] ?? false),
            url: $bucket === 'public' ? $this->filled($state, 's3_public_url') : null,
        );
    }

    /**
     * A trimmed value of the form, or null when it is blank.
     *
     * @param  array<string, mixed>  $state
     */
    private function filled(array $state, string $key): ?string
    {
        $value = Str::trim((string) ($state[$key] ?? ''));

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
        return Arr::join(array_map(fn (AiCapability $capability): string => $capability->label(), $provider->capabilities()), ' · ');
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

    /** How to name a model of a provider whose models are typed, not listed (Azure deployments). */
    private function modelNameHelp(Get $get): ?string
    {
        return app(ProviderAdapters::class)->for($this->chatKind(['provider' => $get('provider')]))->modelNameHelp();
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

            return match ($step) {
                null => "Uses your saved {$account} key".($this->chosenAccount($choice, $get)?->needsBaseUrl() ? ' and endpoint' : ''),
                self::KEY_FIELDS[$choice][1] => "Uses the {$account} key above",
                default => "Uses the {$account} key you entered in {$step}",
            };
        })->description(fn (Get $get): ?string => $this->stepWithTypedKey($choice, $get) === null ? 'You can change it in Settings → AI providers.' : null)
            ->icon(Heroicon::OutlinedKey)
            ->info()
            ->visible(fn (Get $get): bool => $this->reusesEarlierKey($choice, $get) || $this->usesSavedKey($choice, $get));
    }

    /**
     * The step where the key of this field's account is typed; null when it
     * was left blank there (the saved one is used) or not asked. On this
     * field's own step the key field shows above it, typed or not yet: it is
     * asked whenever no key is saved.
     */
    private function stepWithTypedKey(string $choice, Get $get): ?string
    {
        $earlier = $this->earlierKeyField($choice, $get);

        if ($earlier === null) {
            return null;
        }

        $step = array_find(self::KEY_FIELDS, fn (array $field): bool => $field[0] === $earlier)[1] ?? null;

        if ($step === self::KEY_FIELDS[$choice][1]) {
            return $this->chosenAccount($choice, $get)?->isConnected() ? null : $step;
        }

        return filled($get($earlier)) ? $step : null;
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
            ->required(fn (Get $get): bool => blank(config($account($get)?->apiKeyConfig() ?? '')))
            ->placeholder(fn (Get $get): ?string => filled(config($account($get)?->apiKeyConfig() ?? '')) ? '•••••••• saved, leave blank to keep' : null)
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
            'base_url' => match (true) {
                ! $account instanceof AiProvider => Str::rtrim((string) ($state['server_url'] ?? ''), '/'),
                $account->needsBaseUrl() => $this->filled($state, 'chat_endpoint'),
                default => null,
            },
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
        return hash('sha256', Arr::join([$state['provider'] ?? '', $state['server_url'] ?? '', $state['chat_endpoint'] ?? '', $state['api_key'] ?? ''], "\n"));
    }

    private function forgetList(): void
    {
        $this->models = [];
        $this->suggestedModels = [];
        $this->modelsWithoutTools = [];
        $this->modelsReadingImages = [];
        $this->modelsReadingFiles = [];
        $this->listStatus = null;
        $this->listMessage = null;
        $this->listedFor = null;
        $this->data['chat_models'] = [];
        $this->data['default_model'] = null;
        $this->data['quick_task_model'] = null;
        $this->data['vision_model'] = null;
        $this->data['describe_images'] = false;
        $this->data['files_model'] = null;
        $this->data['chat_files'] = false;
        $this->listsVisionModel = false;
        $this->listsFileModel = false;
    }

    /**
     * The chosen chat models that cannot call tools, by name.
     *
     * @return list<string>
     */
    private function chosenWithoutTools(Get $get): array
    {
        return collect((array) $get('chat_models'))
            ->map(fn (mixed $id): string => (string) $id)
            ->intersect($this->modelsWithoutTools)
            ->map(fn (string $id): string => $this->modelName($id))
            ->values()
            ->all();
    }

    /** The warning over the chosen models that cannot call tools, naming them. */
    private function noToolsHeading(Get $get): string
    {
        return Arr::join($this->chosenWithoutTools($get), ', ', ' and ').' cannot call tools';
    }

    /** A listed model's name: its label without the id at its end. */
    private function modelName(string $id): string
    {
        return Str::chopEnd($this->models[$id] ?? $id, " ({$id})");
    }

    /**
     * The listed models for "Manage models", by name with their logos: the
     * chosen ones first, then the suggested ones, then the rest as the
     * provider lists them.
     *
     * @return array<string, string>
     */
    private function modelChoices(): array
    {
        $first = array_flip(array_values(array_unique([...(array) ($this->data['chat_models'] ?? []), ...$this->suggestedModels])));

        return collect(array_keys($this->models))
            ->map(fn (string|int $id): string => (string) $id)
            ->sortBy(fn (string $id, int $position): int => $first[$id] ?? count($first) + $position)
            ->mapWithKeys(fn (string $id): array => [$id => ModelLogo::label($this->modelName($id), $this->chatKind((array) $this->data), $id)])
            ->all();
    }

    /**
     * The listed models that read both PDF files and images, so they can
     * answer about the file a chatter attaches. Only what the provider or
     * models.dev says counts: a model sent a file it cannot read answers
     * about nothing.
     *
     * @return list<string>
     */
    private function fileModels(): array
    {
        return array_values(array_filter(array_map(strval(...), array_keys($this->models)), fn (string $id): bool => collect($this->modelsReadingFiles)->containsStrict($id)));
    }

    /**
     * The help under "Chat file uploads": what it does, and, while it is
     * off, why: the admin turned it off, or no recommended model reads both
     * PDF files and images.
     */
    private function filesHelp(Get $get): string
    {
        $does = 'Lets chatters attach a PDF file or an image in a chat and ask about it. Each agent still allows uploads in its own settings. An agent whose model reads both answers itself; for the others, this model does.';

        if ((bool) $get('chat_files')) {
            return $does;
        }

        $why = $this->listsFileModel
            ? 'Off: no agent can offer uploads.'
            : 'None of the recommended models of this provider reads both, so uploads are off.';

        return "{$does} {$why} You can turn them on later in Settings → AI models → Documents.";
    }

    /**
     * The listed models that can describe images: the ones known to read
     * them, or, when no model is known to, every one not known not to.
     *
     * @return list<string>
     */
    private function imageModels(): array
    {
        $ids = array_map(strval(...), array_keys($this->models));
        $readers = array_values(array_filter($ids, fn (string $id): bool => ($this->modelsReadingImages[$id] ?? null) === true));

        return $readers !== [] ? $readers : array_values(array_filter($ids, fn (string $id): bool => ($this->modelsReadingImages[$id] ?? null) !== false));
    }

    /**
     * The listed models (all of them, or the ids given, in the list's order)
     * as choices: each label with the model's logo.
     *
     * @param  list<string>|null  $ids
     * @return array<string, string>
     */
    private function modelOptions(?array $ids = null): array
    {
        $kind = $this->chatKind((array) $this->data);
        $models = $ids === null ? $this->models : array_intersect_key($this->models, array_flip($ids));

        return collect($models)->map(fn (string $label, string|int $id): string => ModelLogo::label($label, $kind, (string) $id))->all();
    }

    /**
     * Under each model in "Manage models": its id, when its name differs,
     * whether it is suggested, and that tools do not work with it.
     *
     * @return array<string, string>
     */
    private function modelChoiceDescriptions(): array
    {
        return collect(array_keys($this->models))
            ->map(fn (string|int $id): string => (string) $id)
            ->mapWithKeys(fn (string $id): array => [$id => collect([
                $this->modelName($id) === $id ? null : $id,
                collect($this->suggestedModels)->containsStrict($id) ? 'Suggested: calls tools well' : null,
                collect($this->modelsWithoutTools)->containsStrict($id) ? 'No tool calling: tools will not work' : null,
            ])->filter()->implode(' · ')])
            ->filter()
            ->all();
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
     * Keeps how a test answered ({@see ModelCheckData} state) for what was
     * tested and with what (the fingerprint of its service, model and key),
     * so Next and Finish do not ask again. One entry per test: tests that
     * run at once never write the same entry.
     */
    private function rememberCheck(string $what, string $fingerprint, string $state): void
    {
        Cache::put($this->checkCacheKey($what, $fingerprint), $state, now()->addHour());
    }

    /** How a test answered, while it is kept; null when it has not run with these settings. */
    private function rememberedCheck(string $what, string $fingerprint): ?string
    {
        $state = Cache::get($this->checkCacheKey($what, $fingerprint));

        return collect([ModelCheckData::PASSED, ModelCheckData::BUSY, ModelCheckData::FAILED])->containsStrict($state) ? $state : null;
    }

    private function checkCacheKey(string $what, string $fingerprint): string
    {
        return 'admin-setup:checks:'.Filament::auth()->id().':'.hash('sha256', "{$what}\n{$fingerprint}");
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
            collect($fitting)->containsStrict($current) && $current->account()->isConnected() => $current,
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
