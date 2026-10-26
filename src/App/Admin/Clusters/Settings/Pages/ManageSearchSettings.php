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
 * How agents search their sources when someone asks a question: whether
 * they search at all (and may turn it off per agent), hybrid
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
            SettingsCard::make('search', 'Knowledge search', 'Whether agents search their sources before they answer, and whether each agent may turn it off.', Heroicon::OutlinedBookOpen, fn (): array => [
                $this->toggle('search_enabled', 'Search knowledge',
                    help: 'Agents search their sources for each message and answer from what they find. Off: no agent searches, and agents answer with their tools and instructions only.'),
                $this->toggle('search_agent_choice', 'Let workspaces turn it off per agent',
                    help: 'Shows a switch on each agent\'s Sources page, for an agent that only uses tools, like one that looks up payments. Off: every agent searches.'),
            ])->summary(fn (): string => (bool) $this->valueInUse('search_enabled')
                ? 'On · per-agent switch '.$this->shown($this->valueInUse('search_agent_choice'))
                : 'Off for every agent'),
            SettingsCard::make('finding', 'Finding answers', 'Search by meaning and by keywords, and how many results an agent gets.', Heroicon::OutlinedMagnifyingGlass, fn (): array => [
                $this->toggle('hybrid_search', 'Hybrid search',
                    help: 'Finds answers by meaning and by exact words. Helps with names, codes and rare words. Off finds by meaning only.'),
                $this->integer('max_chunks', 'Results per question', help: 'How many pieces of your sources the agent reads to answer one question.', min: 1, max: 20),
            ])->summary(fn (): string => sprintf(
                'Hybrid search %s · %s results per question',
                $this->shown($this->valueInUse('hybrid_search')),
                $this->shown($this->valueInUse('max_chunks')),
            )),
            SettingsCard::make('questions', 'Ambiguous questions', 'How a short follow-up, like "and the price?", is searched, and what the agent says when nothing is found.', Heroicon::OutlinedChatBubbleLeftRight, fn (): array => [
                $this->toggle('query_rewriter', 'Understand follow-up questions',
                    help: 'Turns a short follow-up like "and the price?" into a full question, so the search finds the right answer. Uses one extra AI call.'),
                $this->toggle('query_clarify', 'Ask back when a message is unclear',
                    help: 'When a message is too vague to search, the agent asks what the visitor means instead of saying it has no answer. Needs "Understand follow-up questions".'),
                $this->toggle('scope_hint', 'Say what the agent can help with',
                    help: 'When nothing in the sources matches, the agent tells the visitor what topics it can help with.'),
            ])->summary(fn (): string => 'Understand follow-up questions '.$this->shown($this->valueInUse('query_rewriter'))
                .' · ask back '.$this->shown($this->valueInUse('query_clarify'))
                .' · say what it can help with '.$this->shown($this->valueInUse('scope_hint'))),
            SettingsCard::make('tuning', 'Fine-tuning', 'Search speed, how much text an agent gets, and the minimum scores.', Heroicon::OutlinedAdjustmentsHorizontal, fn (): array => [
                $this->choice('search_mode', 'Search mode', self::SEARCH_MODES,
                    help: sprintf('Trade speed for accuracy on big knowledge bases, over %s pieces. Smaller ones are always searched in full.', number_format((int) config('chatvia.vector.search.exact_max_chunks')))),
                $this->integer('max_context_tokens', 'Most tokens per question', help: 'The most text the results may add to one question, in tokens. A token is about four letters. The best result always goes in. 0 means no limit.', min: 0, max: 32000),
                $this->toggle('relevance_check', 'Leave out weak matches',
                    help: 'Skips results that barely match the question, so a message like "hello" gets none. Off always sends the top results.'),
                $this->decimal('relevance_threshold', 'Minimum score (reranker)', help: 'How well a result must match to be used, from 0 to 1. Higher keeps fewer but better results. Empty uses the model\'s own value.', min: 0, max: 1)
                    ->placeholder(fn (): string => $this->hasReranker() ? (string) RerankModelCatalog::thresholds(app(Reranker::class)->model())->knowledge : '')
                    ->disabled(fn (): bool => ! $this->hasReranker()),
                $this->decimal('similarity_threshold', 'Minimum similarity (no reranker)', help: 'Used when no reranker is set up. How close in meaning a result must be, from 0 to 1. Empty uses the value tested for your model.', min: 0, max: 1)
                    ->placeholder(fn (): string => (string) (EmbeddingProvider::configured()?->similarityThresholds()?->knowledge ?? 'No check')),
                $this->decimal('qa_similarity_threshold', 'Saved answers: minimum similarity', help: 'How close a message must be to a saved question to get its saved answer, from 0 to 1. Higher is safer, since the answer is sent word for word.', min: 0, max: 1)
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
