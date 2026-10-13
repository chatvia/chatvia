<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\SearchSettings;
use BackedEnum;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Filament\Support\Icons\Heroicon;

/**
 * How agents search their knowledge sources, as cards: how results are
 * found, the relevance check, and follow-up questions.
 */
class ManageSearchSettings extends AdminSettingsPage
{
    private const array SEARCH_MODES = [
        'default' => 'Balanced',
        'high_accuracy' => 'More accurate',
        'high_speed' => 'Faster',
    ];

    #[\Override]
    protected static string $settings = SearchSettings::class;

    #[\Override]
    protected static ?string $title = 'Search';

    #[\Override]
    protected static ?string $slug = 'search';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    #[\Override]
    protected static ?int $navigationSort = 6;

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
            SettingsCard::make('results', 'Search', 'How results are found, and how many an agent gets.', Heroicon::OutlinedMagnifyingGlass, fn (): array => [
                $this->toggle('hybrid_search', 'Keyword search',
                    help: 'Search by keywords (BM25) as well as by meaning. Finds exact names, codes and rare words better. You can change this at any time.'),
                $this->choice('search_mode', 'Search mode', self::SEARCH_MODES,
                    help: sprintf('Only for agents with more than %s parts in their sources. Smaller ones are always searched in full.', number_format((int) config('chatvia.vector.search.exact_max_chunks')))),
                $this->integer('max_chunks', 'Results per question', help: 'How many parts of the sources an agent gets for one question.', min: 1, max: 20),
            ])->summary(fn (): string => sprintf(
                'Keyword search %s · %s results per question · %s',
                $this->shown($this->valueInUse('hybrid_search')),
                $this->shown($this->valueInUse('max_chunks')),
                self::SEARCH_MODES[(string) $this->valueInUse('search_mode')] ?? 'Balanced',
            )),
            SettingsCard::make('relevance', 'Relevance check', 'Keeps only the results that answer the question.', Heroicon::OutlinedFunnel, fn (): array => [
                $this->toggle('rerank', 'Relevance check',
                    help: $this->hasReranker()
                        ? 'Drops results that score below the minimum relevance. When off, agents always get the best results, even for "hello".'
                        : 'Needs a reranker, and none is set up (Settings → AI → Services → Reranking). Until then agents get the best results without a check.')
                    ->disabled(fn (): bool => ! $this->hasReranker()),
                $this->decimal('relevance_threshold', 'Minimum relevance', help: 'From 0 to 1. Higher keeps fewer, closer results.', min: 0, max: 1)
                    ->disabled(fn (): bool => ! $this->hasReranker()),
            ])->summary(fn (): string => match (true) {
                ! $this->hasReranker() => 'Needs a reranker',
                (bool) $this->valueInUse('rerank') => 'On · minimum relevance '.$this->shown($this->valueInUse('relevance_threshold')),
                default => 'Off',
            }),
            SettingsCard::make('questions', 'Follow-up questions', 'How a short follow-up is searched.', Heroicon::OutlinedChatBubbleLeftRight, fn (): array => [
                $this->toggle('query_rewriter', 'Rewrite questions',
                    help: 'An AI step turns follow-up questions ("and the price?") into full questions before searching.'),
            ])->summary(fn (): string => 'Rewrite questions '.$this->shown($this->valueInUse('query_rewriter'))),
        ];
    }

    /** Whether the reranker in use (Settings → AI) scores results; the check needs it. */
    private function hasReranker(): bool
    {
        return app(Reranker::class)->isEnabled();
    }
}
