<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\IngestionSettings;
use BackedEnum;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Filament\Forms\Components\CheckboxList;
use Filament\Support\Icons\Heroicon;

/**
 * How knowledge sources come in, as cards: the file types workspaces may
 * upload, the optional AI enhancement of their chunks, and the service that
 * imports websites. How text is split is on {@see ManageChunkingSettings},
 * how agents search it on {@see ManageSearchSettings}.
 */
class ManageIngestionSettings extends AdminSettingsPage
{
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

    #[\Override]
    protected static string $settings = IngestionSettings::class;

    #[\Override]
    protected static ?string $title = 'Ingestion';

    #[\Override]
    protected static ?string $slug = 'ingestion';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    #[\Override]
    protected static ?int $navigationSort = 4;

    public static function description(): string
    {
        return 'How sources come in: file types, AI enhancement and website import.';
    }

    public static function overviewGroup(): string
    {
        return 'AI and knowledge';
    }

    protected function showsValueInUse(string $property): bool
    {
        return $property === 'file_types';
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('file_types', 'File types', 'The files workspaces can upload as knowledge.', Heroicon::OutlinedDocument, fn (): array => [
                CheckboxList::make('file_types')
                    ->label('Accepted file types')
                    ->helperText('Files already uploaded keep working when you turn their type off. Images need a document reader that reads images, and audio and video a speech-to-text service (Settings → AI): without one they are not offered.')
                    ->options(self::FILE_TYPES)
                    ->descriptions($this->fileTypeExtensions())
                    ->columns(2)
                    ->bulkToggleable()
                    ->required()
                    ->validationMessages(['required' => 'Choose at least one file type.'])
                    // In the order of the list, so an unchanged choice equals the default.
                    ->dehydrateStateUsing(fn (?array $state): array => array_values(array_intersect(array_keys(self::FILE_TYPES), $state ?? []))),
            ])->summary(function (): string {
                $types = array_intersect_key(self::FILE_TYPES, array_flip((array) $this->valueInUse('file_types')));

                return count($types) === count(self::FILE_TYPES) ? 'All file types' : implode(', ', $types);
            }),
            SettingsCard::make('enhancement', 'AI enhancement', 'Optional AI steps while sources are trained.', Heroicon::OutlinedSparkles, fn (): array => [
                $this->toggle('enhance_chunks', 'Enhance files and web pages',
                    help: 'Adds a summary and likely questions to every chunk of uploaded files and crawled pages, and a summary of the whole source. One model call per chunk.'),
                $this->toggle('enhance_text_chunks', 'Enhance text sources',
                    help: 'The same step for pasted texts. They are short, so it costs little. With both off, training makes no model calls.'),
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

    /**
     * The extensions of each file type, shown under its checkbox.
     *
     * @return array<string, string>
     */
    private function fileTypeExtensions(): array
    {
        $extensions = [];

        foreach (KnowledgeFileType::cases() as $type) {
            $extensions[$type->value] = '.'.implode(', .', $type->extensions());
        }

        return $extensions;
    }
}
