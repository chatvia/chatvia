<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\Concerns\BuildsChunkingCards;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\KnowledgeSettings;
use BackedEnum;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Knowledge\Embedding\Rerank\RerankModelCatalog;
use Domain\Knowledge\Embedding\Services\RelevanceThresholds;
use Domain\Knowledge\Ingestion\Services\DocumentReaders;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Enums\AiCapability;
use Domain\Llm\Enums\AiProvider;
use Filament\Forms\Components\CheckboxList;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * What agents learn from, and how they search it. Everyday cards: the file
 * types people may upload, website import (Firecrawl), the AI enhancement
 * of chunks, search (keyword search and how many results), and follow-up
 * questions. Under Advanced: how text is split into chunks, a preview of
 * the splitting, and the fine-tuning of search (mode, token budget, the
 * relevance check and its minimum scores).
 */
class ManageKnowledgeSettings extends AdminSettingsPage
{
    use BuildsChunkingCards;

    /** The label of each KnowledgeFileType, in the order the list shows them. */
    private const array FILE_TYPES = [
        'pdf' => 'PDF',
        'word' => 'Word',
        'spreadsheet' => 'Spreadsheets',
        'presentation' => 'Presentations',
        'text' => 'Text and Markdown',
        'ebook' => 'Ebooks',
        'image' => 'Images',
        'audio' => 'Audio',
        'video' => 'Video',
    ];

    /** The document readers, as the file types name them. */
    private const array READERS = [
        'mistral' => 'Mistral OCR',
        'azure' => 'Azure Document Intelligence',
    ];

    private const array SEARCH_MODES = [
        'default' => 'Balanced',
        'high_accuracy' => 'More accurate',
        'high_speed' => 'Faster',
    ];

    #[\Override]
    protected static string $settings = KnowledgeSettings::class;

    #[\Override]
    protected static ?string $title = 'Knowledge';

    #[\Override]
    protected static ?string $slug = 'knowledge';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    #[\Override]
    protected static ?int $navigationSort = 3;

    public static function description(): string
    {
        return 'What agents learn from, and how they search it.';
    }

    public static function overviewGroup(): string
    {
        return 'AI and knowledge';
    }

    /**
     * Whether websites can be imported: a half set-up Firecrawl first.
     *
     * @return array{0: string, 1: string}
     */
    #[\Override]
    public function overviewStatus(): array
    {
        return $this->unfinishedStatus()
            ?? (filled($this->valueInUse('firecrawl_api_key')) ? ['Website import on', 'success'] : ['Website import off', 'gray']);
    }

    protected function showsValueInUse(string $property): bool
    {
        return in_array($property, ['file_types', 'chunk_unit'], true);
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('file_types', 'File types', 'Which files people can upload as knowledge, and what reads each one.', Heroicon::OutlinedDocumentDuplicate, fn (): array => [
                CheckboxList::make('file_types')
                    ->label('Accepted file types')
                    ->helperText('Files already uploaded keep working when you turn their type off. A greyed-out type cannot be read now: PDF, Word, slides and images need a document reader, and audio and video speech to text (Settings → AI models). It is not offered until then.')
                    ->options(self::FILE_TYPES)
                    ->descriptions($this->fileTypeDescriptions())
                    ->disableOptionWhen(fn (string $value): bool => ! KnowledgeFileType::from($value)->isReadable())
                    // Every type stays valid, so a saved type that cannot be read now is kept.
                    ->in(array_keys(self::FILE_TYPES))
                    ->columns(2)
                    ->bulkToggleable()
                    ->required()
                    ->validationMessages(['required' => 'Choose at least one file type.'])
                    // In the order of the list, so an unchanged choice equals the default.
                    ->dehydrateStateUsing(fn (?array $state): array => array_values(array_intersect(array_keys(self::FILE_TYPES), $state ?? []))),
            ])->summary(fn (): string => $this->fileTypesSummary()),
            SettingsCard::make('website_import', 'Website import', 'Lets people add websites as knowledge. Uses Firecrawl.', Heroicon::OutlinedGlobeAlt, fn (): array => [
                $this->secret('firecrawl_api_key', 'Firecrawl API key'),
            ])->advancedFields(fn (): array => [
                $this->url('firecrawl_endpoint', 'Address', help: 'Change it only if you run your own Firecrawl server.'),
                $this->integer('firecrawl_rate_per_minute', 'Pages per minute',
                    help: 'About 80% of the per-minute limit of your Firecrawl plan. 0 means no limit.', min: 0),
            ])->required('firecrawl_api_key')->console('https://www.firecrawl.dev/app/api-keys', 'Get a key'),
            SettingsCard::make('enhancement', 'AI enhancement', 'An optional AI step while sources are trained. Helps agents find the right part.', Heroicon::OutlinedCpuChip, fn (): array => [
                $this->toggle('enhance_chunks', 'Enhance files and web pages',
                    help: 'Adds a summary and likely questions to every chunk of uploaded files and crawled pages, and a summary of the whole source, so far more of the right passages are found. One Smart-model call per chunk while training: a 300-page PDF makes about 300 calls.'),
                $this->toggle('enhance_text_chunks', 'Enhance text sources',
                    help: 'The same step for pasted texts. They are short, so it costs little. With both off, training makes no model calls.'),
            ])->summary(fn (): string => sprintf(
                'Files and pages %s · Texts %s',
                $this->shown($this->valueInUse('enhance_chunks')),
                $this->shown($this->valueInUse('enhance_text_chunks')),
            )),
            SettingsCard::make('search', 'Search', 'How agents find the parts of their sources that answer a question.', Heroicon::OutlinedMagnifyingGlass, fn (): array => [
                $this->toggle('hybrid_search', 'Keyword search',
                    help: 'Also search by words, not only by meaning. Finds names, codes and rare words better. You can change this at any time.'),
                $this->integer('max_chunks', 'Results per question', help: 'How many parts of the sources an agent gets for one question.', min: 1, max: 20),
            ])->summary(fn (): string => sprintf(
                'Keyword search %s · %s results per question',
                $this->shown($this->valueInUse('hybrid_search')),
                $this->shown($this->valueInUse('max_chunks')),
            )),
            SettingsCard::make('questions', 'Follow-up questions', 'How a short follow-up, like "and the price?", is searched.', Heroicon::OutlinedChatBubbleLeftRight, fn (): array => [
                $this->toggle('query_rewriter', 'Understand follow-up questions',
                    help: 'An AI step turns a follow-up ("and the price?") into a full question from the chat before it, only for the search. One Fast-model call per follow-up.'),
            ])->summary(fn (): string => 'Understand follow-up questions '.$this->shown($this->valueInUse('query_rewriter'))),
            SettingsCard::make('splitting', 'Splitting', 'How texts are cut into chunks (small parts). The defaults suit most agents.', Heroicon::OutlinedScissors, fn (): array => $this->splittingFields())
                ->properties('chunk_unit', 'chunk_size', 'chunk_overlap', 'markdown_strategy', 'text_strategy', 'transcript_strategy', 'tokenizer')
                ->summary(fn (): string => $this->sizeSummary())
                ->advanced(),
            SettingsCard::make('try_it', 'Try splitting', 'Split a sample, or your own text, with the options above. Nothing is saved.', Heroicon::OutlinedBeaker, fn (): array => [
                $this->previewSection(),
            ])->properties()
                ->summary(fn (): string => 'Test the options before you save')
                ->advanced(),
            SettingsCard::make('search_tuning', 'Search fine-tuning', 'Search speed, how much text an agent gets, and the minimum scores.', Heroicon::OutlinedAdjustmentsHorizontal, fn (): array => [
                $this->choice('search_mode', 'Search mode', self::SEARCH_MODES,
                    help: sprintf('Only for agents with more than %s parts in their sources. Smaller ones are always searched in full.', number_format((int) config('chatvia.vector.search.exact_max_chunks')))),
                $this->integer('max_context_tokens', 'Most tokens per question', help: 'The most text the results of one question may add, in tokens. Lower-ranked results past it are left out; the best one always goes in. 0 means no limit.', min: 0, max: 32000),
                $this->toggle('relevance_check', 'Leave out weak matches',
                    help: 'Results that do not match the question well are left out, so "hello" gets none. When off, agents always get the top results.'),
                $this->decimal('relevance_threshold', 'Minimum score (reranker)', help: 'The reranker\'s score, from 0 to 1. Higher keeps fewer, closer results. Empty uses the value set for the model.', min: 0, max: 1)
                    ->placeholder(fn (): string => $this->hasReranker() ? (string) RerankModelCatalog::thresholds(app(Reranker::class)->model())->knowledge : '')
                    ->disabled(fn (): bool => ! $this->hasReranker()),
                $this->decimal('similarity_threshold', 'Minimum similarity (no reranker)', help: 'For the basic check, without a reranker: how close in meaning, from 0 to 1. Empty uses the value measured for your embedding model; a model never measured gets no check.', min: 0, max: 1)
                    ->placeholder(fn (): string => (string) (EmbeddingProvider::configured()?->similarityThresholds()?->knowledge ?? 'No check')),
                $this->decimal('qa_similarity_threshold', 'Saved answers: minimum similarity', help: 'A message gets a saved Q&A answer when it is one of its questions, or this close to one in meaning (0 to 1). Higher is safer: the saved answer is sent word for word. Empty uses the value measured for your embedding model.', min: 0, max: 1)
                    ->placeholder(fn (): string => (string) (EmbeddingProvider::configured()?->qaSimilarityThreshold() ?? 'Whole questions only')),
            ])->summary(fn (): string => (self::SEARCH_MODES[(string) $this->valueInUse('search_mode')] ?? 'Balanced').' · '.match (true) {
                ! (bool) $this->valueInUse('relevance_check') => 'weak matches kept',
                $this->hasReranker() => 'reranker, minimum '.RelevanceThresholds::knowledgeRerank(app(Reranker::class)),
                RelevanceThresholds::knowledgeSimilarity() !== null => 'basic check, minimum '.RelevanceThresholds::knowledgeSimilarity(),
                default => 'no check measured',
            })->advanced(),
        ];
    }

    /**
     * Under each file type: its extensions, and what reads them now.
     *
     * @return array<string, Htmlable>
     */
    private function fileTypeDescriptions(): array
    {
        $descriptions = [];

        foreach (KnowledgeFileType::cases() as $type) {
            $descriptions[$type->value] = new HtmlString(e('.'.implode(', .', $type->extensions())).'<br>'.e($this->readBy($type)));
        }

        return $descriptions;
    }

    /**
     * What reads files of the type now: speech to text, the document reader
     * (only the formats it takes; images it does not take are turned into
     * PNG for it), or the app itself.
     */
    private function readBy(KnowledgeFileType $type): string
    {
        if ($type->isMedia()) {
            $speech = $this->speechService();

            return $speech instanceof AiProvider ? "Turned into text by {$speech->label()}" : 'Needs speech to text (Settings → AI models)';
        }

        if (! $type->isReadable()) {
            return 'Needs a document reader (Settings → AI models)';
        }

        $readers = app(DocumentReaders::class);
        $driver = $readers->available() ? $readers->driver() : 'none';
        $groups = collect($type->extensions())->groupBy(fn (string $extension): string => $driver !== 'none' && ($type === KnowledgeFileType::Image || DocumentReaders::readsItself($driver, $extension))
            ? self::READERS[$driver]
            : 'the app');

        return $groups->count() === 1
            ? 'Read by '.$groups->keys()->first()
            : $groups->map(fn (Collection $extensions, string $reader): string => '.'.implode(', .', $extensions->all()).' by '.$reader)->join(' · ');
    }

    /** The speech to text service in use, with "Automatic" resolved; null when off. */
    private function speechService(): ?AiProvider
    {
        if (! app(Transcriber::class)->isEnabled()) {
            return null;
        }

        $driver = strtolower((string) config('chatvia.ai.transcriber', 'auto'));

        return $driver === 'auto' ? AiProvider::firstConnectedFor(AiCapability::Speech) : AiProvider::tryFrom($driver);
    }

    /** The types people can upload now, and how many chosen ones cannot be read yet. */
    private function fileTypesSummary(): string
    {
        $chosen = array_intersect_key(self::FILE_TYPES, array_flip((array) $this->valueInUse('file_types')));
        $readable = array_filter($chosen, fn (string $label, string $value): bool => KnowledgeFileType::from($value)->isReadable(), ARRAY_FILTER_USE_BOTH);
        $summary = match (true) {
            count($readable) === count(self::FILE_TYPES) => 'All file types',
            $readable === [] => 'None can be read now',
            default => implode(', ', $readable),
        };
        $waiting = count($chosen) - count($readable);

        return $waiting > 0 ? "{$summary} · {$waiting} cannot be read yet" : $summary;
    }

    /** Whether the reranker in use (Settings → AI models) scores results. */
    private function hasReranker(): bool
    {
        return app(Reranker::class)->isEnabled();
    }
}
