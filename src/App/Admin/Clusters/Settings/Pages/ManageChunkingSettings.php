<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\ChunkingSettings;
use BackedEnum;
use Closure;
use Domain\Knowledge\Ingestion\Data\Chunking\ChunkerConfigData;
use Domain\Knowledge\Ingestion\Data\Chunking\ChunkerServerData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Enums\ChunkerOption;
use Domain\Knowledge\Ingestion\Enums\ChunkerStrategy;
use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit\ChunkFitClient;
use Domain\Knowledge\Ingestion\Services\TokenCounter;
use Domain\Knowledge\Ingestion\Services\TokenSplitter;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * How text files, ebooks and web pages are split into chunks, as cards: the
 * splitter and tokenizer, the chunker server, the chunker's strategy and its
 * options (with a preview that splits a sample before anything is saved), and
 * the built-in splitter. The strategies and tokenizers come from what the
 * chunker server reports (GET /v1/engines, asked at most once a minute).
 */
class ManageChunkingSettings extends AdminSettingsPage
{
    /** How many chunks the preview shows. */
    private const int PREVIEW_LIMIT = 12;

    private const array SPLITTERS = ['auto' => 'Automatic', 'chunkfit' => 'Chunker', 'local' => 'Built-in'];

    private const string SAMPLE = <<<'MD'
        # Returns and refunds

        You can return most items within 30 days of delivery. The item must be unused and in its original packaging. Gift cards and personalised items cannot be returned.

        ## How to start a return

        1. Sign in to your account and open **Orders**.
        2. Choose the order and click **Return an item**.
        3. Print the label and drop the parcel at any post office.

        We email you when the parcel arrives. Most returns are checked within three working days.

        ## Refunds

        Refunds go back to the card you paid with. They take five to ten working days to show on your statement, depending on your bank. Shipping costs are refunded only when the item was faulty or we sent the wrong one.

        ## Faulty items

        If an item arrives damaged, send us a photo within 48 hours. We replace it for free, or refund it in full if it is out of stock.
        MD;

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
     * The last preview: the splitter used, the chunks (text and tokens) and
     * any error.
     *
     * @var array{splitter: string, strategy: string, chunks: list<array{text: string, tokens: int}>, error: ?string}|null
     */
    public ?array $preview = null;

    public static function description(): string
    {
        return 'How text is split into chunks, and the tokenizer.';
    }

    public static function overviewGroup(): string
    {
        return 'AI and knowledge';
    }

    protected function showsValueInUse(string $property): bool
    {
        return in_array($property, ['text_splitter', 'strategy', 'length_unit'], true);
    }

    protected function cards(): array
    {
        $usesChunker = fn (): bool => $this->valueInUse('text_splitter') !== 'local';

        return [
            SettingsCard::make('splitter', 'Splitter and tokenizer', 'What splits the text, and the tokenizer that counts every size.', Heroicon::OutlinedScissors, fn (): array => [
                ToggleButtons::make('text_splitter')
                    ->label('Split text with')
                    ->options(self::SPLITTERS)
                    ->icons(['auto' => Heroicon::OutlinedSparkles, 'chunkfit' => Heroicon::OutlinedCpuChip, 'local' => Heroicon::OutlinedCube])
                    ->in(array_keys(self::SPLITTERS))
                    ->inline()
                    ->live()
                    ->helperText(fn (Get $get): string => match ($get('text_splitter')) {
                        'chunkfit' => 'The chunker (github.com/chatvia/chunker): many strategies, from fixed token windows to semantic chunks.',
                        'local' => 'Runs inside Chatvia with no other service: whole paragraphs, packed up to a number of tokens.',
                        default => 'The chunker when it is set up, otherwise the built-in splitter.',
                    }),
                Select::make('tokenizer')
                    ->label('Tokenizer')
                    ->options(fn (): array => $this->tokenizers())
                    ->in(fn (): array => array_keys($this->tokenizers()))
                    ->placeholder(fn (): string => 'Default: '.$this->defaultValue('tokenizer'))
                    ->helperText('Counts the tokens of chunk sizes and of every stored chunk. The chunker counts them; when it cannot, Chatvia\'s own tokenizer counts with the same one. cl100k_base suits most models.'),
            ])->summary(fn (): string => sprintf(
                '%s · %s',
                self::SPLITTERS[(string) $this->valueInUse('text_splitter')] ?? 'Automatic',
                $this->valueInUse('tokenizer'),
            )),
            SettingsCard::make('server', 'Chunker server', 'Where the chunker runs: the hosted chunk.fit or your own.', Heroicon::OutlinedServerStack, fn (): array => [
                $this->url('chunker_url', 'Address',
                    help: 'The hosted chunk.fit, or your own chunker, such as http://chunker:8000 in the Docker stack.'),
                $this->secret('chunker_api_key', 'API key',
                    help: 'Only for the hosted chunk.fit. It is never sent to another address.', required: false),
            ])->console('https://chunk.fit')
                ->summary(fn (): string => $this->serverStatus())
                ->visible($usesChunker),
            SettingsCard::make('strategy', 'Strategy', 'How the chunker decides where a chunk ends, its options, and a preview on your own text.', Heroicon::OutlinedSquares2x2, fn (): array => [
                ...$this->strategyFields(),
                $this->previewSection(),
            ])->properties('strategy', ...array_column(ChunkerOption::cases(), 'value'))
                ->summary(fn (): string => $this->strategySummary())
                ->visible($usesChunker),
            SettingsCard::make('built_in', 'Built-in splitter', 'Packs whole paragraphs, and the sentences of very long ones, into chunks.', Heroicon::OutlinedCube, fn (): array => [
                $this->integer('local_chunk_tokens', 'Chunk size', min: 50, max: 8000)->suffix('tokens'),
                $this->integer('local_overlap_tokens', 'Overlap', min: 0, max: 2000)->suffix('tokens'),
            ])->summary(fn (): string => sprintf('%s tokens, %s overlap', $this->valueInUse('local_chunk_tokens'), $this->valueInUse('local_overlap_tokens')))
                ->visible(fn (): bool => $this->valueInUse('text_splitter') !== 'chunkfit'),
        ];
    }

    // --- Strategy ------------------------------------------------------------

    /**
     * @return list<Component>
     */
    private function strategyFields(): array
    {
        return [
            ToggleButtons::make('engine')
                ->label('Library')
                ->options(collect(['langchain', 'llamaindex', 'chonkie'])->mapWithKeys(fn (string $engine): array => [$engine => ChunkerStrategy::engineLabel($engine)])->all())
                ->inline()
                ->live()
                ->dehydrated(false)
                ->afterStateHydrated(fn (ToggleButtons $component, Get $get): ToggleButtons => $component->state($this->strategyFrom($get)->engine()))
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    $strategies = ChunkerStrategy::forEngine((string) $state);
                    $runnable = array_values(array_filter($strategies, fn (ChunkerStrategy $strategy): bool => $this->server()->cannotRun($strategy) === null));

                    $set('strategy', ($runnable[0] ?? $strategies[0] ?? ChunkerStrategy::LangchainRecursive)->value);
                }),
            Radio::make('strategy')
                ->label('Strategy')
                ->options(fn (Get $get): array => collect($this->engineStrategies($get))->mapWithKeys(fn (ChunkerStrategy $strategy): array => [$strategy->value => $strategy->label()])->all())
                ->descriptions(fn (Get $get): array => collect($this->engineStrategies($get))->mapWithKeys(fn (ChunkerStrategy $strategy): array => [
                    $strategy->value => $strategy->description().(($reason = $this->server()->cannotRun($strategy)) !== null ? " {$reason}" : ''),
                ])->all())
                ->disableOptionWhen(fn (string $value): bool => ($strategy = ChunkerStrategy::tryFrom($value)) !== null && $this->server()->cannotRun($strategy) !== null)
                ->in(array_column(ChunkerStrategy::cases(), 'value'))
                ->live(),
            Grid::make(2)->schema([
                $this->option(ChunkerOption::ChunkSize, $this->integer('chunk_size', 'Chunk size', min: 1)
                    ->suffix(fn (Get $get): string => $this->unit($get))
                    ->helperText('The largest a chunk may be.')),
                $this->option(ChunkerOption::ChunkOverlap, $this->integer('chunk_overlap', 'Overlap', min: 0)
                    ->suffix(fn (Get $get): string => $this->unit($get))
                    ->helperText('Repeated from the end of one chunk at the start of the next.')
                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $size = $this->optionValue(ChunkerOption::ChunkSize, $get);

                        if (is_numeric($value) && is_numeric($size) && (int) $value >= (int) $size) {
                            $fail('The overlap must be smaller than the chunk size.');
                        }
                    })),
                $this->option(ChunkerOption::LengthUnit, ToggleButtons::make('length_unit')
                    ->label('Measure sizes in')
                    ->options(['tokens' => 'Tokens', 'characters' => 'Characters'])
                    ->in(['tokens', 'characters'])
                    ->inline()
                    ->live()),
                $this->option(ChunkerOption::Language, $this->choice('language', 'Split by the structure of', ChunkerOption::LANGUAGES,
                    help: 'Headings, blocks and code first, then paragraphs. Markdown suits web pages and ebooks.')
                    ->searchable()),
                $this->option(ChunkerOption::Separators, TagsInput::make('separators')
                    ->label('Separators')
                    ->helperText('Tried in this order when no structure is chosen. Type \n for a line break.')
                    ->placeholder('Add a separator')
                    ->reorderable()
                    ->columnSpanFull()),
                $this->option(ChunkerOption::Separator, $this->text('separator', 'Separator', help: 'Type \n for a line break.')),
                $this->option(ChunkerOption::MaxHeaderLevel, $this->integer('max_header_level', 'Deepest heading level', help: 'Headings down to this level (1 = #, 6 = ######) start a new chunk.', min: 1, max: 6)),
                $this->option(ChunkerOption::StripHeaders, $this->toggle('strip_headers', 'Remove headings from the chunk text',
                    help: 'The headings are kept as information of the chunk either way.')),
                $this->option(ChunkerOption::BufferSize, $this->integer('buffer_size', 'Sentences compared together',
                    help: 'Neighbouring sentences grouped when the meaning is compared.', min: 0)),
                $this->option(ChunkerOption::BreakpointPercentileThreshold, $this->integer('breakpoint_percentile_threshold', 'Break sensitivity', help: 'Percentile from 1 to 99. Higher makes fewer, longer chunks.', min: 1, max: 99)),
                $this->option(ChunkerOption::Threshold, $this->decimal('threshold', 'Similarity threshold', help: 'From 0 to 1. Sentences stay together while their similarity is above it. Higher makes shorter chunks.', min: 0.01, max: 1)),
                $this->option(ChunkerOption::SimilarityWindow, $this->integer('similarity_window', 'Similarity window',
                    help: 'How many sentences are compared at a time.', min: 1)),
                $this->option(ChunkerOption::MinSentencesPerChunk, $this->integer('min_sentences_per_chunk', 'Fewest sentences per chunk', min: 1)),
                $this->option(ChunkerOption::MinCharactersPerChunk, $this->integer('min_characters_per_chunk', 'Fewest characters per chunk', min: 1)),
                Text::make('This strategy has no options: it follows the Markdown headings of the text.')
                    ->color('gray')
                    ->visible(fn (Get $get): bool => $this->strategyFrom($get)->options() === [])
                    ->columnSpanFull(),
            ]),
        ];
    }

    private function strategySummary(): string
    {
        $strategy = ChunkerStrategy::tryFrom((string) $this->valueInUse('strategy')) ?? ChunkerStrategy::LangchainRecursive;
        $parts = ["{$strategy->label()} ({$this->engineName($strategy)})"];

        if ($strategy->accepts(ChunkerOption::ChunkSize)) {
            $size = $this->valueInUse('chunk_size') ?? $strategy->defaultFor(ChunkerOption::ChunkSize);
            $unit = $strategy->accepts(ChunkerOption::LengthUnit) ? (string) ($this->valueInUse('length_unit') ?? 'tokens') : 'tokens';
            $parts[] = filled($size) ? "{$size} {$unit}" : 'whole sections';
        }

        if ($strategy->accepts(ChunkerOption::ChunkOverlap)) {
            $overlap = (int) ($this->valueInUse('chunk_overlap') ?? $strategy->defaultFor(ChunkerOption::ChunkOverlap));
            $parts[] = $overlap === 0 ? 'no overlap' : "{$overlap} overlap";
        }

        if (($reason = $this->server()->cannotRun($strategy)) !== null) {
            $parts[] = $reason;
        }

        return implode(' · ', $parts);
    }

    private function engineName(ChunkerStrategy $strategy): string
    {
        return ChunkerStrategy::engineLabel($strategy->engine());
    }

    // --- Preview -------------------------------------------------------------

    private function previewSection(): Section
    {
        return Section::make('Try it')
            ->description('Splits a sample with the options above, before you save them. Nothing is stored.')
            ->icon(Heroicon::OutlinedBeaker)
            ->compact()
            ->schema([
                Textarea::make('preview_text')
                    ->hiddenLabel()
                    ->rows(8)
                    ->dehydrated(false)
                    ->afterStateHydrated(fn (Textarea $component, ?string $state): Textarea => $component->state(filled($state) ? $state : self::SAMPLE)),
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
     * Split the sample with the panel's (unsaved) options and the saved
     * chunker server.
     */
    private function runPreview(Get $get): void
    {
        abort_unless($this->canEdit(), 403);

        $strategy = $this->strategyFrom($get);
        $splitter = $this->valueInUse('text_splitter') === 'local' || ! app(ChunkFitClient::class)->isAvailable() ? 'local' : 'chunkfit';

        try {
            $chunks = $splitter === 'local'
                ? $this->localChunks((string) $get('preview_text'))
                : $this->chunkerChunks((string) $get('preview_text'), $strategy, $get);

            $this->preview = ['splitter' => $splitter, 'strategy' => $strategy->label(), 'chunks' => $chunks, 'error' => null];
        } catch (Throwable $e) {
            $this->preview = ['splitter' => $splitter, 'strategy' => $strategy->label(), 'chunks' => [], 'error' => $e->getMessage()];
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

        $tokens = array_column($chunks, 'tokens');
        $shown = array_slice($chunks, 0, self::PREVIEW_LIMIT);

        return [
            Callout::make(sprintf(
                '%d %s with the %s',
                count($chunks),
                Str::plural('chunk', count($chunks)),
                $this->preview['splitter'] === 'local' ? 'built-in splitter' : $this->preview['strategy'].' strategy',
            ))
                ->description(sprintf('%d to %d tokens, %d on average.', min($tokens), max($tokens), (int) round(array_sum($tokens) / count($tokens))))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->success(),
            Grid::make(['default' => 1, 'md' => 2])->schema(array_map(
                fn (array $chunk, int $index): Section => Section::make('Chunk '.($index + 1))
                    ->compact()
                    ->afterHeader([Text::make("{$chunk['tokens']} tokens")->badge()->color('gray')])
                    ->schema([Text::make(Str::limit($chunk['text'], 500))->size(TextSize::Small)]),
                $shown,
                array_keys($shown),
            )),
            ...(count($chunks) > self::PREVIEW_LIMIT
                ? [Text::make(sprintf('And %d more.', count($chunks) - self::PREVIEW_LIMIT))->color('gray')]
                : []),
        ];
    }

    /**
     * @return list<array{text: string, tokens: int}>
     */
    private function chunkerChunks(string $text, ChunkerStrategy $strategy, Get $get): array
    {
        $size = $this->optionValue(ChunkerOption::ChunkSize, $get);
        $overlap = $this->optionValue(ChunkerOption::ChunkOverlap, $get);

        if ($strategy->accepts(ChunkerOption::ChunkOverlap) && is_numeric($size) && is_numeric($overlap) && $overlap >= $size) {
            throw new InvalidArgumentException('The overlap must be smaller than the chunk size.');
        }

        $options = [];

        foreach (ChunkerOption::cases() as $option) {
            $options[$option->value] = $this->optionValue($option, $get);
        }

        $config = ChunkerConfigData::fromOptions($strategy, (string) $this->valueInUse('tokenizer'), $options);

        return app(ChunkFitClient::class)
            ->chunk($text, $config)
            ->map(fn ($chunk): array => ['text' => $chunk->text, 'tokens' => $chunk->tokenCount])
            ->all();
    }

    /**
     * @return list<array{text: string, tokens: int}>
     */
    private function localChunks(string $text): array
    {
        $splitter = new TokenSplitter(
            chunkSize: max((int) $this->valueInUse('local_chunk_tokens'), 1),
            chunkOverlap: max((int) $this->valueInUse('local_overlap_tokens'), 0),
        );

        $texts = array_values(array_filter(
            array_map(static fn (PageData $page): string => trim($page->content), $splitter->split($text)),
            static fn (string $chunk): bool => $chunk !== '',
        ));

        $counts = app(TokenCounter::class)->countMany($texts);

        return array_map(fn (string $chunk, int $tokens): array => ['text' => $chunk, 'tokens' => $tokens], $texts, $counts);
    }

    // --- Helpers -------------------------------------------------------------

    /**
     * Show an option's field only for the strategies that accept it, with the
     * default that applies: Chatvia's, or else the chunker's own.
     */
    private function option(ChunkerOption $option, Field $field): Field
    {
        $field->visible(fn (Get $get): bool => $this->strategyFrom($get)->accepts($option));

        if ($field instanceof TextInput || $field instanceof Select) {
            $field->placeholder(function (Get $get) use ($option): ?string {
                $default = $this->defaultValue($option->value) ?? $this->strategyFrom($get)->defaultFor($option);

                return match (true) {
                    $option === ChunkerOption::Language => 'Default: '.(ChunkerOption::LANGUAGES[(string) $default] ?? 'none, use the separators'),
                    is_bool($default) => $default ? 'On' : 'Off',
                    is_scalar($default) => (string) $default,
                    default => null,
                };
            });
        }

        return $field;
    }

    /**
     * The value an option has in the panel: the field, else Chatvia's
     * default. Null means the chunker's own default.
     */
    private function optionValue(ChunkerOption $option, Get $get): mixed
    {
        return ChunkingSettings::cast($option->value, $get($option->value)) ?? $this->defaultValue($option->value);
    }

    private function strategyFrom(Get $get): ChunkerStrategy
    {
        return ChunkerStrategy::tryFrom((string) ($get('strategy') ?? $this->valueInUse('strategy'))) ?? ChunkerStrategy::LangchainRecursive;
    }

    /**
     * @return list<ChunkerStrategy>
     */
    private function engineStrategies(Get $get): array
    {
        return ChunkerStrategy::forEngine((string) ($get('engine') ?? $this->strategyFrom($get)->engine()));
    }

    private function unit(Get $get): string
    {
        return $this->strategyFrom($get)->accepts(ChunkerOption::LengthUnit) && $this->optionValue(ChunkerOption::LengthUnit, $get) === 'characters'
            ? 'characters'
            : 'tokens';
    }

    /**
     * The tokenizers the chunker reports, or the ones Chatvia knows while it
     * cannot be asked.
     *
     * @return array<string, string>
     */
    private function tokenizers(): array
    {
        $names = $this->server()->encodings ?: array_keys(ChunkerOption::ENCODINGS);

        return collect($names)->mapWithKeys(fn (string $name): array => [$name => ChunkerOption::ENCODINGS[$name] ?? $name])->all();
    }

    /**
     * What the saved chunker server can run, asked at most once a minute.
     */
    private function server(): ChunkerServerData
    {
        if ($this->valueInUse('text_splitter') === 'local' && ! app(ChunkFitClient::class)->isAvailable()) {
            return ChunkerServerData::unreachable('The chunker is not set up.');
        }

        return Cache::remember($this->serverCacheKey(), 60, fn (): ChunkerServerData => app(ChunkFitClient::class)->server());
    }

    private function serverCacheKey(): string
    {
        return 'admin:chunker-server:'.md5((string) config('services.chunkfit.base_url'));
    }

    private function serverStatus(): string
    {
        $server = $this->server();

        if (! $server->isReachable()) {
            return "Cannot reach the chunker: {$server->error}";
        }

        return sprintf(
            'Connected: %d strategies. Embeddings: %s.',
            count($server->strategies),
            $server->embeddingsConfigured ? ($server->embeddingsModel ?? 'set up') : 'not set up, so semantic strategies are off',
        );
    }
}
