<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\KnowledgeSettings;
use BackedEnum;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Knowledge\Embedding\Rerank\RerankModelCatalog;
use Domain\Knowledge\Embedding\Services\RelevanceThresholds;
use Filament\Support\Icons\Heroicon;

/**
 * How agents search their sources when someone asks a question: hybrid
 * search and how many results, and follow-up questions. Under Advanced:
 * the fine-tuning (mode, token budget, the relevance check and its minimum
 * scores). Its own page, apart from Knowledge, since searching is not what
 * agents learn from. The values are part of KnowledgeSettings; this page
 * saves only its own fields.
 */
class ManageSearchSettings extends AdminSettingsPage
{
    private const array SEARCH_MODES = [
        'default' => 'Balanced',
        'high_accuracy' => 'More accurate',
        'high_speed' => 'Faster',
    ];

    #[\Override]
    protected static string $settings = KnowledgeSettings::class;

    #[\Override]
    protected static ?string $title = 'Search';

    #[\Override]
    protected static ?string $slug = 'search';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    #[\Override]
    protected static ?int $navigationSort = 5;

    public static function description(): string
    {
        return 'How agents find the parts of their sources that answer a question.';
    }

    public static function overviewGroup(): string
    {
        return 'AI and knowledge';
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('finding', 'Finding answers', 'Search by meaning and by keywords, and how many results an agent gets.', Heroicon::OutlinedMagnifyingGlass, fn (): array => [
                $this->toggle('hybrid_search', 'Hybrid search',
                    help: 'Search by meaning and by keywords together. Finds names, codes and rare words better. Off searches by meaning only. You can change this at any time.'),
                $this->integer('max_chunks', 'Results per question', help: 'How many parts of the sources an agent gets for one question.', min: 1, max: 20),
            ])->summary(fn (): string => sprintf(
                'Hybrid search %s · %s results per question',
                $this->shown($this->valueInUse('hybrid_search')),
                $this->shown($this->valueInUse('max_chunks')),
            )),
            SettingsCard::make('questions', 'Follow-up questions', 'How a short follow-up, like "and the price?", is searched.', Heroicon::OutlinedChatBubbleLeftRight, fn (): array => [
                $this->toggle('query_rewriter', 'Understand follow-up questions',
                    help: 'An AI step turns a follow-up ("and the price?") into a full question from the chat before it, only for the search. One model call per follow-up. The model is set in Settings → AI models → Models for app tasks.'),
            ])->summary(fn (): string => 'Understand follow-up questions '.$this->shown($this->valueInUse('query_rewriter'))),
            SettingsCard::make('tuning', 'Fine-tuning', 'Search speed, how much text an agent gets, and the minimum scores.', Heroicon::OutlinedAdjustmentsHorizontal, fn (): array => [
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

    /** Whether the reranker in use (Settings → AI models) scores results. */
    private function hasReranker(): bool
    {
        return app(Reranker::class)->isEnabled();
    }
}
