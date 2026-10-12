<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\AiSettings;
use BackedEnum;
use Closure;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Knowledge\Embedding\Exceptions\EmbeddingDimensionsMismatchException;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Llm\Models\Llm;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;

/**
 * Which AI service handles each step, the catalog model behind each of the
 * app's own jobs (LlmRole), and the key, address and models of the services
 * that are not chat providers.
 *
 * The embedding provider and its models decide the stored vectors: once a
 * source is trained they are locked, and a provider that cannot return the
 * stored vector size is refused.
 */
class ManageAiSettings extends AdminSettingsPage
{
    private const string LOCKED_HELP = 'Locked: sources are already trained with it. Changing it needs every source trained again.';

    #[\Override]
    protected static string $settings = AiSettings::class;

    #[\Override]
    protected static ?string $title = 'AI';

    #[\Override]
    protected static ?string $slug = 'ai';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    #[\Override]
    protected static ?int $navigationSort = 3;

    private ?bool $hasEmbeddings = null;

    public static function description(): string
    {
        return 'Chat and embedding providers, models and API keys.';
    }

    public static function overviewGroup(): string
    {
        return 'AI and knowledge';
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('services', 'Services', 'Which service handles search vectors, ranking, documents and speech.', Heroicon::OutlinedAdjustmentsHorizontal, fn (): array => [
                $this->embeddingChoice(),
                $this->choice('reranker', 'Reranking', [
                    'auto' => 'Automatic (the embedding provider\'s own)',
                    'qwen' => 'Qwen3 on Scaleway AI',
                    'cohere' => 'Cohere',
                    'none' => 'Off',
                ], help: 'Scores search results and tools against the question.'),
                $this->choice('document_parser', 'Document reading', [
                    'auto' => 'Automatic',
                    'mistral' => 'Mistral OCR (also reads images)',
                    'local' => 'Local tools (text only)',
                ], help: 'Reads PDFs, office files and images.'),
                $this->choice('transcriber', 'Speech to text', [
                    'auto' => 'Automatic',
                    'scaleway' => 'Scaleway AI (Whisper)',
                    'none' => 'Off',
                ], help: 'Voice input and audio sources.'),
                $this->choice('document_llm', 'Whole-PDF reading', [
                    'auto' => 'Automatic',
                    'mistral' => 'Mistral',
                    'none' => 'Off',
                ], help: 'Document summaries and chat with PDF files. Off turns those two features off.'),
            ])->summary(fn (): string => 'Embeddings: '.(EmbeddingProvider::tryFrom((string) $this->valueInUse('embedding_provider'))?->label() ?? $this->shown($this->valueInUse('embedding_provider')))),
            SettingsCard::make('models', 'Models', 'The catalog model behind each job, how much history agents see, and the PDF models.', Heroicon::OutlinedCpuChip, fn (): array => [
                $this->modelChoice('default_agent_model', 'Chat',
                    help: 'New agents start with it, and the analytics assistant uses it.',
                    unset: 'The first model of the catalog'),
                $this->modelChoice('fast_model', 'Fast',
                    help: 'Short, frequent calls: conversation titles, topics, tone, guardrails, starters, translation and the eval judge. A small, cheap model.'),
                $this->modelChoice('smart_model', 'Smart',
                    help: 'Reading content: page summaries and tables while sources are trained.'),
                $this->modelChoice('vision_model', 'Vision',
                    help: 'Describes the images inside documents. Needs a model that reads images.'),
                $this->integer('history_messages', 'Messages of history', help: 'How many recent messages of a conversation the model sees on each turn.', min: 1, max: 50),
                $this->text('summary_model', 'Document summary model'),
                $this->text('analysis_model', 'Document analysis model'),
                $this->text('document_chat_model', 'PDF chat model',
                    help: 'Answers questions about PDF files in a chat. Needs PDF reading and function calling.'),
            ])->summary(fn (): string => 'Chat: '.($this->catalogName($this->valueInUse('default_agent_model')) ?? 'the first model of the catalog')),
            SettingsCard::make('scaleway', 'Scaleway AI', 'Qwen3 embeddings, reranking and speech to text. Its chat models are added as a provider.', Heroicon::OutlinedCpuChip, fn (): array => [
                $this->secret('scaleway_api_key', 'API key'),
                $this->url('scaleway_endpoint', 'Address',
                    help: 'To bill a specific project, use https://api.scaleway.ai/{project-id}/v1.'),
                $this->embeddingModel('scaleway_embedding_model'),
                $this->text('scaleway_rerank_model', 'Rerank model'),
                $this->text('scaleway_transcription_model', 'Speech-to-text model'),
            ])->required('scaleway_api_key')->console('https://console.scaleway.com/iam/api-keys'),
            SettingsCard::make('mistral', 'Mistral', 'Document OCR, whole-PDF reading and embeddings. Its chat models are added as a provider.', asset('images/llms/mistral.svg'), fn (): array => [
                $this->secret('mistral_api_key', 'API key'),
                $this->url('mistral_base_url', 'Address'),
                $this->text('mistral_ocr_model', 'OCR model'),
                $this->embeddingModel('mistral_embedding_model'),
            ])->required('mistral_api_key')->console('https://console.mistral.ai/api-keys'),
            SettingsCard::make('cohere', 'Cohere', 'Embeddings and reranking.', asset('images/llms/cohere.svg'), fn (): array => [
                $this->secret('cohere_api_key', 'API key'),
                $this->url('cohere_api_endpoint', 'Address'),
                $this->embeddingModel('cohere_embedding_model'),
                $this->text('cohere_rerank_model', 'Rerank model'),
            ])->required('cohere_api_key')->console('https://dashboard.cohere.com/api-keys'),
            SettingsCard::make('jina', 'Jina', 'Embeddings.', Heroicon::OutlinedCubeTransparent, fn (): array => [
                $this->secret('jina_api_key', 'API key'),
                $this->url('jina_api_endpoint', 'Address'),
                $this->embeddingModel('jina_embedding_model'),
            ])->required('jina_api_key'),
        ];
    }

    /**
     * A catalog model, by its alias, for one of the app's jobs. Unset uses
     * the chat model.
     */
    private function modelChoice(string $property, string $label, string $help, string $unset = 'Same as the chat model'): Select
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

    private function embeddingChoice(): Field
    {
        $providers = array_column(array_map(
            fn (EmbeddingProvider $provider): array => [$provider->value, $provider->label()],
            EmbeddingProvider::cases(),
        ), 1, 0);

        return $this->choice('embedding_provider', 'Embeddings', $providers)
            ->disabled(fn (): bool => $this->hasEmbeddings())
            ->helperText(fn (): string => $this->hasEmbeddings()
                ? self::LOCKED_HELP
                : 'Turns text into vectors for search. It must return the vector size this install stores.')
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                $error = filled($value) ? $this->dimensionsError((string) $value) : null;

                if ($error !== null) {
                    $fail($error);
                }
            });
    }

    private function embeddingModel(string $property): Field
    {
        return $this->text($property, 'Embedding model')
            ->disabled(fn (): bool => $this->hasEmbeddings())
            ->helperText(fn (): ?string => $this->hasEmbeddings() ? self::LOCKED_HELP : null);
    }

    private function hasEmbeddings(): bool
    {
        return $this->hasEmbeddings ??= Chunk::query()->exists();
    }

    /**
     * Why the provider cannot be used with the stored vectors, or null: the
     * same check the app makes when it builds the provider.
     */
    private function dimensionsError(string $value): ?string
    {
        $provider = EmbeddingProvider::tryFrom($value);

        if ($provider === null) {
            return null;
        }

        $embeddings = app($provider->providerClass());
        $stored = app(EmbeddingStorage::class)->dimensions();

        return $embeddings->getDimensions() === $stored
            ? null
            : EmbeddingDimensionsMismatchException::for($embeddings, $stored)->getMessage();
    }
}
