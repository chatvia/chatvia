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
use Filament\Forms\Components\Field;
use Filament\Support\Icons\Heroicon;

/**
 * Which AI service handles each step, the models of the internal tasks, and
 * the key, address and models of each AI provider.
 *
 * The embedding provider and its models decide the stored vectors: once a
 * source is trained they are locked, and a provider that cannot return the
 * stored vector size is refused.
 */
class ManageAiSettings extends AdminSettingsPage
{
    private const string LOCKED_HELP = 'Locked: sources are already trained with it. Changing it needs every source trained again.';

    private const array CHAT_PROVIDERS = [
        'scaleway' => 'Scaleway AI',
        'mistral' => 'Mistral',
        'openrouter' => 'OpenRouter',
        'groq' => 'Groq',
    ];

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
            SettingsCard::make('services', 'Services', 'Which service handles chat, search vectors, ranking, documents and speech.', Heroicon::OutlinedAdjustmentsHorizontal, fn (): array => [
                $this->choice('llm_provider', 'Chat', self::CHAT_PROVIDERS, help: 'Where agent replies and the internal AI tasks go.'),
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
            ])->summary(fn (): string => sprintf(
                'Chat: %s · Embeddings: %s',
                self::CHAT_PROVIDERS[(string) $this->valueInUse('llm_provider')] ?? $this->shown($this->valueInUse('llm_provider')),
                EmbeddingProvider::tryFrom((string) $this->valueInUse('embedding_provider'))?->label() ?? $this->shown($this->valueInUse('embedding_provider')),
            )),
            SettingsCard::make('models', 'Models', 'The model new agents start with, how much history they see, and the PDF models.', Heroicon::OutlinedCpuChip, fn (): array => [
                $this->text('default_agent_model', 'Model of new agents',
                    help: 'The internal name of a model in the model catalog. New agents start with it.'),
                $this->integer('history_messages', 'Messages of history', help: 'How many recent messages of a conversation the model sees on each turn.', min: 1, max: 50),
                $this->text('summary_model', 'Document summary model'),
                $this->text('analysis_model', 'Document analysis model'),
                $this->text('document_chat_model', 'PDF chat model',
                    help: 'Answers questions about PDF files in a chat. Needs PDF reading and function calling.'),
            ])->summary(fn (): string => 'New agents: '.$this->shown($this->valueInUse('default_agent_model'))),
            SettingsCard::make('scaleway', 'Scaleway AI', 'Chat, Qwen3 embeddings, reranking and speech to text.', Heroicon::OutlinedCpuChip, fn (): array => [
                $this->secret('scaleway_api_key', 'API key'),
                $this->url('scaleway_endpoint', 'Address',
                    help: 'To bill a specific project, use https://api.scaleway.ai/{project-id}/v1.'),
                $this->embeddingModel('scaleway_embedding_model'),
                $this->text('scaleway_rerank_model', 'Rerank model'),
                $this->text('scaleway_transcription_model', 'Speech-to-text model'),
            ])->required('scaleway_api_key')->console('https://console.scaleway.com/iam/api-keys'),
            SettingsCard::make('mistral', 'Mistral', 'Chat, document OCR and whole-PDF reading.', asset('images/llms/mistral.svg'), fn (): array => [
                $this->secret('mistral_api_key', 'API key'),
                $this->url('mistral_base_url', 'Address'),
                $this->text('mistral_ocr_model', 'OCR model'),
                $this->embeddingModel('mistral_embedding_model'),
            ])->required('mistral_api_key')->console('https://console.mistral.ai/api-keys'),
            SettingsCard::make('openrouter', 'OpenRouter', 'Chat models from many providers through one key.', Heroicon::OutlinedArrowsRightLeft, fn (): array => [
                $this->secret('openrouter_api_key', 'API key'),
                $this->url('openrouter_base_url', 'Address'),
                $this->url('openrouter_completion_endpoint', 'Chat completions address'),
            ])->required('openrouter_api_key')->console('https://openrouter.ai/settings/keys'),
            SettingsCard::make('groq', 'Groq', 'Fast chat models.', Heroicon::OutlinedBolt, fn (): array => [
                $this->secret('groq_api_key', 'API key'),
                $this->url('groq_base_url', 'Address'),
                $this->url('groq_completion_endpoint', 'Chat completions address'),
            ])->required('groq_api_key')->console('https://console.groq.com/keys'),
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
