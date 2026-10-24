<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\KnowledgeSettings;
use BackedEnum;
use Domain\Knowledge\Document\Conversion\ConversionPrograms;
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
use Illuminate\Support\Str;

/**
 * What agents learn from: the file types people may upload, website import
 * (Firecrawl) and the AI enhancement of chunks. How text is split into
 * chunks (ManageSplittingSettings) and how agents search it
 * (ManageSearchSettings) have their own pages.
 */
class ManageKnowledgeSettings extends AdminSettingsPage
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

    /** The document readers, as the file types name them. */
    private const array READERS = [
        'mistral' => 'Mistral OCR',
        'azure' => 'Azure Document Intelligence',
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
        return 'What agents learn from.';
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
        return $property === 'file_types';
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
                    help: 'Adds a summary and likely questions to every chunk of uploaded files and crawled pages, and a summary of the whole source, so far more of the right passages are found. One model call per chunk while training: a 300-page PDF makes about 300 calls. The model is set in Settings → AI models → Models for app tasks.'),
                $this->toggle('enhance_text_chunks', 'Enhance text sources',
                    help: 'The same step for pasted texts. They are short, so it costs little. With both off, training makes no model calls.'),
                $this->toggle('describe_images', 'Describe images',
                    help: 'While files are enhanced, describes the pictures inside PDF files and other documents, so search can find what they show. Needs a model that reads images: Settings → AI models → Models for app tasks → Image descriptions. The setup turns this off when your AI provider has no such model. Off: pictures are skipped, and the text is still read.'),
            ])->summary(fn (): string => sprintf(
                'Files and pages %s · Texts %s · Images %s',
                $this->shown($this->valueInUse('enhance_chunks')),
                $this->shown($this->valueInUse('enhance_text_chunks')),
                $this->shown($this->valueInUse('describe_images')),
            )),
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

        $readBy = $groups->count() === 1
            ? 'Read by '.$groups->keys()->first()
            : $groups->map(fn (Collection $extensions, string $reader): string => '.'.implode(', .', $extensions->all()).' by '.$reader)->join(' · ');

        return $readBy.$this->missingPrograms($type);
    }

    /**
     * The formats of the type this server cannot read, because their program
     * is not installed (Settings → Platform → Programs), such as
     * " · .doc needs catdoc, which is not found". Empty when there are none.
     */
    private function missingPrograms(KnowledgeFileType $type): string
    {
        $programs = app(ConversionPrograms::class);

        return collect($type->extensions())
            ->groupBy(fn (string $extension): string => (string) $programs->missingFor($extension))
            ->except('')
            ->map(fn (Collection $extensions, string $program): string => ' · .'.implode(', .', $extensions->all())." needs {$program}, which is not found")
            ->join('');
    }

    /** The speech to text service in use, with "Automatic" resolved; null when off. */
    private function speechService(): ?AiProvider
    {
        if (! app(Transcriber::class)->isEnabled()) {
            return null;
        }

        $driver = Str::lower((string) config('chatvia.ai.transcriber', 'auto'));

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
}
