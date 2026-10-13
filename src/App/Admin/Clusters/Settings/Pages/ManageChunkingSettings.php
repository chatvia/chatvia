<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\ChunkingSettings;
use BackedEnum;
use Closure;
use Domain\Knowledge\Ingestion\Data\Chunking\ChunkingConfigData;
use Domain\Knowledge\Ingestion\Enums\ChunkSizeUnit;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Enums\SplitStrategy;
use Domain\Knowledge\Ingestion\Services\Chunking\BuiltinTextSplitter;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\CharacterMeasure;
use Domain\Llm\Services\TikToken;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * How the text agents learn from is split into chunks, as cards: the chunk
 * size and its unit (tokens or characters), how each kind of content is
 * split (with a preview on a sample, before anything is saved), and, under
 * Advanced, the strategy for each kind and the tokenizer.
 */
class ManageChunkingSettings extends AdminSettingsPage
{
    /** How many chunks the preview shows. */
    private const int PREVIEW_LIMIT = 12;

    /**
     * The sample of each kind of content the preview offers, in resources/:
     * the same fictional shop as a help centre in Markdown (about 2,000
     * tokens), a typed policy text, and a video transcript.
     */
    private const array SAMPLES = [
        'markdown' => 'chunking/markdown.md',
        'text' => 'chunking/text.txt',
        'transcript' => 'chunking/transcript.txt',
    ];

    #[\Override]
    protected static string $settings = ChunkingSettings::class;

    #[\Override]
    protected static ?string $title = 'Chunking';

    #[\Override]
    protected static ?string $slug = 'chunking';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScissors;

    #[\Override]
    protected static ?int $navigationSort = 5;

    /**
     * The last preview: the kind of content, the strategy, the unit, the
     * chunks (text, tokens and characters) and any error.
     *
     * @var array{kind: string, strategy: string, unit: string, chunks: list<array{text: string, tokens: int, characters: int}>, error: ?string}|null
     */
    public ?array $preview = null;

    public static function description(): string
    {
        return 'How the text your agents learn from is split into chunks.';
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
            SettingsCard::make('size', 'Chunk size', 'How long a chunk may be, in tokens or characters.', Heroicon::OutlinedArrowsPointingIn, fn (): array => [
                ToggleButtons::make('chunk_unit')
                    ->label('Measure in')
                    ->options(collect(ChunkSizeUnit::cases())->mapWithKeys(fn (ChunkSizeUnit $unit): array => [$unit->value => $unit->label()])->all())
                    ->in(array_column(ChunkSizeUnit::cases(), 'value'))
                    ->inline()
                    ->live()
                    ->helperText('Tokens are how AI models read text, counted with the tokenizer under Advanced. Characters are easier to picture.'),
                Grid::make(2)->schema([
                    $this->sizeField('chunk_size', 'Chunk size', fn (ChunkSizeUnit $unit): int => $unit->minSize(), fn (ChunkSizeUnit $unit): int => $unit->maxSize())
                        ->placeholder(fn (Get $get): string => (string) $this->unit($get)->defaultSize())
                        ->helperText(fn (Get $get): string => $this->unit($get) === ChunkSizeUnit::Tokens
                            ? 'The largest a chunk may be. 300 to 500 tokens suits most agents.'
                            : 'The largest a chunk may be. 1,200 to 2,000 characters suits most agents.'),
                    $this->sizeField('chunk_overlap', 'Overlap', fn (): int => 0, fn (ChunkSizeUnit $unit): int => $unit->maxOverlap())
                        ->helperText('Repeated from the end of one chunk at the start of the next. Most text needs none.')
                        ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            if (is_numeric($value) && (int) $value >= $this->chunkSize($get)) {
                                $fail('The overlap must be smaller than the chunk size.');
                            }
                        }),
                ]),
            ])->properties('chunk_unit', 'chunk_size', 'chunk_overlap')
                ->summary(fn (): string => $this->sizeSummary()),
            SettingsCard::make('content', 'How content is split', 'Each kind of content is split by its own structure. Try it on your own text.', Heroicon::OutlinedSquares2x2, fn (): array => [
                Grid::make(['default' => 1, 'md' => 2])->schema([
                    ...array_map($this->kindSection(...), ContentKind::cases()),
                    Section::make('Documents')
                        ->description('PDF, Word, slides, spreadsheets and images')
                        ->compact()
                        ->schema([
                            Text::make('One chunk per page')->weight(FontWeight::Medium),
                            Text::make('Each page is read on its own and is not split further.')->color('gray')->size(TextSize::Small),
                        ]),
                ]),
                $this->previewSection(),
            ])->properties()
                ->summary(fn (): string => collect(ContentKind::cases())
                    ->map(fn (ContentKind $kind): string => "{$kind->label()}: {$kind->strategy()->label()}")
                    ->push('Documents: one chunk per page')
                    ->join(' · ')),
            SettingsCard::make('advanced', 'Advanced', 'The strategy for each kind of content, and the tokenizer. The defaults suit most agents.', Heroicon::OutlinedAdjustmentsHorizontal, fn (): array => [
                ...array_map($this->strategyField(...), ContentKind::cases()),
                $this->choice('tokenizer', 'Tokenizer', TikToken::ENCODINGS,
                    help: 'Counts tokens: the chunk size when it is in tokens, and the size of every stored chunk. cl100k_base suits most models.'),
            ])->collapsed(),
        ];
    }

    /**
     * A size field whose limits and suffix follow the unit on the page.
     *
     * @param  Closure(ChunkSizeUnit): int  $min
     * @param  Closure(ChunkSizeUnit): int  $max
     */
    private function sizeField(string $property, string $label, Closure $min, Closure $max): TextInput
    {
        return $this->integer($property, $label)
            ->minValue(fn (Get $get): int => $min($this->unit($get)))
            ->maxValue(fn (Get $get): int => $max($this->unit($get)))
            ->suffix(fn (Get $get): string => $this->unit($get)->suffix());
    }

    // --- Kinds of content ----------------------------------------------------

    /** A kind of content, its sources and the strategy in use. */
    private function kindSection(ContentKind $kind): Section
    {
        $strategy = $kind->strategy();

        return Section::make($kind->label())
            ->description($kind->sources())
            ->compact()
            ->schema([
                Text::make($strategy->label())->weight(FontWeight::Medium),
                Text::make($strategy->description())->color('gray')->size(TextSize::Small),
            ]);
    }

    /** The strategy for a kind: only the ones that suit it. */
    private function strategyField(ContentKind $kind): Select
    {
        $property = "{$kind->value}_strategy";
        $options = collect($kind->strategies())->mapWithKeys(fn (SplitStrategy $strategy): array => [$strategy->value => $strategy->label()])->all();

        return $this->choice($property, $kind->label(), $options)
            ->hint($kind->sources())
            ->live()
            ->helperText(fn (Get $get): string => $this->strategyFor($kind, $get)->description());
    }

    // --- Preview -------------------------------------------------------------

    private function previewSection(): Section
    {
        return Section::make('Try it')
            ->description('Splits a sample, or your own text, with the options on this page before you save them. Nothing is stored.')
            ->icon(Heroicon::OutlinedBeaker)
            ->compact()
            ->schema([
                ToggleButtons::make('preview_kind')
                    ->label('Split as')
                    ->options(collect(ContentKind::cases())->mapWithKeys(fn (ContentKind $kind): array => [$kind->value => $kind->label()])->all())
                    ->inline()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateHydrated(fn (ToggleButtons $component, ?string $state): ToggleButtons => $component->state($state ?? ContentKind::Markdown->value))
                    ->afterStateUpdated(fn (Get $get, Set $set, ?string $state) => $this->switchSample($get, $set, $state)),
                Textarea::make('preview_text')
                    ->hiddenLabel()
                    ->rows(14)
                    ->dehydrated(false)
                    ->afterStateHydrated(fn (Textarea $component, ?string $state): Textarea => $component->state(filled($state) ? $state : $this->sample(ContentKind::Markdown))),
                Actions::make([
                    Action::make('runPreview')
                        ->label('Split this text')
                        ->icon(Heroicon::OutlinedPlay)
                        ->action(fn (Get $get) => $this->runPreview($get)),
                ])->key('preview-actions'),
                Grid::make(1)->schema(fn (): array => $this->previewResult()),
            ]);
    }

    /**
     * A new kind to split as: show that kind's sample, unless the text box
     * holds the admin's own text, and clear the result of the last preview.
     */
    private function switchSample(Get $get, Set $set, ?string $state): void
    {
        $this->preview = null;

        $text = self::normalized((string) $get('preview_text'));
        $samples = array_map(fn (ContentKind $kind): string => self::normalized($this->sample($kind)), ContentKind::cases());

        if ($text === '' || in_array($text, $samples, true)) {
            $set('preview_text', $this->sample(ContentKind::tryFrom((string) $state) ?? ContentKind::Markdown));
        }
    }

    private function sample(ContentKind $kind): string
    {
        return File::get(resource_path(self::SAMPLES[$kind->value]));
    }

    /** Text as the browser may send it back: other line endings, trimmed. */
    private static function normalized(string $text): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $text));
    }

    /** Split the sample with the page's (unsaved) unit, size, overlap, strategy and tokenizer. */
    private function runPreview(Get $get): void
    {
        $kind = ContentKind::tryFrom((string) $get('preview_kind')) ?? ContentKind::Markdown;
        $strategy = $this->strategyFor($kind, $get);
        $unit = $this->unit($get);

        try {
            $config = new ChunkingConfigData(
                kind: $kind,
                strategy: $strategy,
                unit: $unit,
                size: $this->chunkSize($get),
                overlap: $this->chunkOverlap($get),
                encoding: $this->tokenizer($get),
            );

            if ($config->overlap >= $config->size) {
                throw new InvalidArgumentException('The overlap must be smaller than the chunk size.');
            }

            $characters = new CharacterMeasure;
            $chunks = array_map(fn (string $chunk): array => [
                'text' => $chunk,
                'tokens' => TikToken::countTokens($chunk, $config->encoding),
                'characters' => $characters->length($chunk),
            ], app(BuiltinTextSplitter::class)->splitWith((string) $get('preview_text'), $config));

            $this->preview = ['kind' => $kind->label(), 'strategy' => $strategy->label(), 'unit' => $unit->value, 'chunks' => $chunks, 'error' => null];
        } catch (Throwable $e) {
            $this->preview = ['kind' => $kind->label(), 'strategy' => $strategy->label(), 'unit' => $unit->value, 'chunks' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * @return list<Component>
     */
    private function previewResult(): array
    {
        if ($this->preview === null) {
            return [];
        }

        if ($this->preview['error'] !== null) {
            return [
                Callout::make('The text could not be split')
                    ->description($this->preview['error'])
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->danger(),
            ];
        }

        $chunks = $this->preview['chunks'];

        if ($chunks === []) {
            return [Callout::make('No chunks: the text is empty.')->icon(Heroicon::OutlinedInformationCircle)->info()];
        }

        $unit = ChunkSizeUnit::tryFrom($this->preview['unit']) ?? ChunkSizeUnit::Tokens;
        $sizes = array_column($chunks, $unit === ChunkSizeUnit::Tokens ? 'tokens' : 'characters');
        $shown = array_slice($chunks, 0, self::PREVIEW_LIMIT);

        return [
            Callout::make(sprintf('%d %s, split as %s with %s', count($chunks), Str::plural('chunk', count($chunks)), $this->preview['kind'], $this->preview['strategy']))
                ->description(sprintf('%d to %d %s, %d on average.', min($sizes), max($sizes), $unit->suffix(), (int) round(array_sum($sizes) / count($sizes))))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->success(),
            Grid::make(['default' => 1, 'md' => 2])->schema(array_map(
                fn (array $chunk, int $index): Section => Section::make('Chunk '.($index + 1))
                    ->key("preview-chunk-{$index}")
                    ->compact()
                    ->afterHeader([Text::make(sprintf('%d %s', $unit === ChunkSizeUnit::Tokens ? $chunk['tokens'] : $chunk['characters'], $unit->suffix()))->badge()->color('gray')])
                    ->schema([Text::make(Str::limit($chunk['text'], 500))->size(TextSize::Small)])
                    ->footer([$this->viewChunkAction($chunk, $index)]),
                $shown,
                array_keys($shown),
            )),
            ...(count($chunks) > self::PREVIEW_LIMIT
                ? [Text::make(sprintf('And %d more.', count($chunks) - self::PREVIEW_LIMIT))->color('gray')]
                : []),
        ];
    }

    /**
     * A link that opens the whole chunk in a modal: the card shows only its
     * start, without its line breaks.
     *
     * @param  array{text: string, tokens: int, characters: int}  $chunk
     */
    private function viewChunkAction(array $chunk, int $index): Action
    {
        return Action::make('viewChunk')
            ->label('View whole chunk')
            ->icon(Heroicon::OutlinedArrowsPointingOut)
            ->link()
            ->size(Size::Small)
            ->modalHeading('Chunk '.($index + 1))
            ->modalDescription("{$chunk['tokens']} tokens, {$chunk['characters']} characters")
            ->modalContent(view('filament.settings.chunk', ['text' => $chunk['text']]))
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    // --- Values on the page --------------------------------------------------

    /** The strategy the page has for a kind: the field, else the default; only one that suits the kind. */
    private function strategyFor(ContentKind $kind, Get $get): SplitStrategy
    {
        $property = "{$kind->value}_strategy";
        $strategy = SplitStrategy::tryFrom((string) ($get($property) ?: $this->defaultValue($property)));

        return $strategy instanceof SplitStrategy && $strategy->suits($kind) ? $strategy : $kind->defaultStrategy();
    }

    private function unit(Get $get): ChunkSizeUnit
    {
        return ChunkSizeUnit::tryFrom((string) ($get('chunk_unit') ?: $this->defaultValue('chunk_unit'))) ?? ChunkSizeUnit::Tokens;
    }

    /** The size on the page, else the default, else the unit's default. */
    private function chunkSize(Get $get): int
    {
        $size = ChunkingSettings::cast('chunk_size', $get('chunk_size')) ?? $this->defaultValue('chunk_size');

        return is_numeric($size) && (int) $size > 0 ? (int) $size : $this->unit($get)->defaultSize();
    }

    private function chunkOverlap(Get $get): int
    {
        return (int) (ChunkingSettings::cast('chunk_overlap', $get('chunk_overlap')) ?? $this->defaultValue('chunk_overlap'));
    }

    private function tokenizer(Get $get): string
    {
        return TikToken::encoding((string) ($get('tokenizer') ?: $this->defaultValue('tokenizer')));
    }

    private function sizeSummary(): string
    {
        $config = ChunkingConfigData::forKind(ContentKind::Text);
        $suffix = $config->unit->suffix();

        return sprintf('Up to %d %s · %s', $config->size, $suffix, $config->overlap === 0 ? 'no overlap' : "{$config->overlap} {$suffix} overlap");
    }
}
