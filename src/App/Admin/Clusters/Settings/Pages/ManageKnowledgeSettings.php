<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\KnowledgeSettings;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * How agents search their knowledge sources, the optional AI steps of
 * ingestion, and the service that imports websites.
 */
class ManageKnowledgeSettings extends AdminSettingsPage
{
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
    protected static ?int $navigationSort = 4;

    public static function description(): string
    {
        return 'How agents search, ingestion steps and website import.';
    }

    public static function overviewGroup(): string
    {
        return 'AI and knowledge';
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('search', 'Search', 'How agents find the parts of their sources that answer a question.', Heroicon::OutlinedMagnifyingGlass, fn (): array => [
                $this->toggle('hybrid_search', 'Keyword search',
                    help: 'Search by keywords (BM25) as well as by meaning. Finds exact names, codes and rare words better. You can change this at any time.'),
                $this->toggle('rerank', 'Relevance check',
                    help: 'Drops results that score below the minimum relevance. When off, agents always get the best results, even for "hello".'),
                $this->choice('search_mode', 'Search mode', self::SEARCH_MODES),
                $this->decimal('relevance_threshold', 'Minimum relevance', help: 'From 0 to 1. Higher keeps fewer, closer results.', min: 0, max: 1),
                $this->integer('max_chunks', 'Results per question', help: 'How many parts of the sources an agent gets for one question.', min: 1, max: 20),
                $this->toggle('query_rewriter', 'Rewrite questions',
                    help: 'An AI step turns follow-up questions ("and the price?") into full questions before searching.'),
            ])->summary(fn (): string => sprintf(
                'Keyword search %s · %s results per question · %s',
                $this->shown($this->valueInUse('hybrid_search')),
                $this->shown($this->valueInUse('max_chunks')),
                self::SEARCH_MODES[(string) $this->valueInUse('search_mode')] ?? 'Balanced',
            )),
            SettingsCard::make('ingestion', 'Ingestion', 'Optional AI steps while sources are trained.', Heroicon::OutlinedArrowDownTray, fn (): array => [
                $this->toggle('enhance_chunks', 'Enhance files and web pages',
                    help: 'Adds a summary and likely questions to every chunk of uploaded files and crawled pages. One model call per chunk.'),
                $this->toggle('enhance_text_chunks', 'Enhance text sources',
                    help: 'The same step for pasted texts. They are short, so it costs little.'),
            ])->summary(fn (): string => sprintf(
                'Files and pages %s · Texts %s',
                $this->shown($this->valueInUse('enhance_chunks')),
                $this->shown($this->valueInUse('enhance_text_chunks')),
            )),
            SettingsCard::make('firecrawl', 'Firecrawl', 'Imports websites as knowledge sources.', Heroicon::OutlinedFire, fn (): array => [
                $this->secret('firecrawl_api_key', 'API key'),
                $this->url('firecrawl_endpoint', 'Address'),
                $this->integer('firecrawl_rate_per_minute', 'Requests per minute',
                    help: 'About 80% of the per-minute limit of your Firecrawl plan. 0 means no limit.', min: 0),
            ])->required('firecrawl_api_key')->console('https://www.firecrawl.dev/app/api-keys'),
        ];
    }
}
