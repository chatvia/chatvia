<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\Concerns\BuildsChunkingCards;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\KnowledgeSettings;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * How texts are cut into chunks before agents search them: the chunk size
 * and its unit, the smallest chunk, the strategy for each kind of content, the tokenizer, and a
 * preview of the unsaved options. Its own page, apart from Knowledge, since
 * it is a topic of its own and the preview needs room. The values are part
 * of KnowledgeSettings; this page saves only its own fields.
 */
class ManageSplittingSettings extends AdminSettingsPage
{
    use BuildsChunkingCards;

    #[\Override]
    protected static string $settings = KnowledgeSettings::class;

    #[\Override]
    protected static ?string $title = 'Splitting';

    #[\Override]
    protected static ?string $slug = 'splitting';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScissors;

    #[\Override]
    protected static ?int $navigationSort = 4;

    public static function description(): string
    {
        return 'How texts are cut into chunks before agents search them.';
    }

    public static function overviewGroup(): string
    {
        return 'AI and knowledge';
    }

    protected function showsValueInUse(string $property): bool
    {
        return $property === 'chunk_unit';
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('splitting', 'Splitting', 'How texts are cut into chunks (small parts). The defaults suit most agents.', Heroicon::OutlinedScissors, fn (): array => $this->splittingFields())
                ->properties('chunk_unit', 'chunk_size', 'chunk_overlap', 'chunk_minimum', 'markdown_strategy', 'text_strategy', 'transcript_strategy', 'tokenizer')
                ->summary(fn (): string => $this->sizeSummary()),
            SettingsCard::make('try_it', 'Try splitting', 'Split a sample, or your own text, with the options above. Nothing is saved.', Heroicon::OutlinedBeaker, fn (): array => [
                $this->previewSection(),
            ])->properties()
                ->summary(fn (): string => 'Test the options before you save'),
        ];
    }
}
