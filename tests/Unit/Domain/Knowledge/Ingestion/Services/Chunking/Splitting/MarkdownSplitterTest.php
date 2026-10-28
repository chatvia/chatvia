<?php

use Domain\Knowledge\Ingestion\Contracts\ChunkMeasure;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\CharacterMeasure;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\MarkdownSplitter;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\RecursiveSplitter;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\TokenMeasure;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The Markdown splitter follows the structure of the page: one section per
 * heading, small sections together, long sections cut between blocks, code
 * blocks and tables kept whole when they fit, and the headings above every
 * chunk repeated at its start.
 */
function mdsSplit(string $markdown, int $size, int $overlap = 0, ?ChunkMeasure $measure = null, int $minimum = 0): array
{
    return new MarkdownSplitter(new RecursiveSplitter($measure ?? new CharacterMeasure, $size, $overlap, $minimum))->split($markdown);
}

it('keeps a page that fits as one chunk', function (): void {
    expect(mdsSplit("# Help\n\nWelcome.\n\n## Returns\n\nAny item.", 200))->toBe(["# Help\n\nWelcome.\n\n## Returns\n\nAny item."])
        ->and(mdsSplit(" \n\n ", 200))->toBe([]);
});

it('starts each section with its heading, and packs small sections together', function (): void {
    $markdown = <<<'MD'
        # Returns

        You can return any item within 30 days of delivery.

        # Shipping

        We ship in two days.

        # Payment

        We take cards.
        MD;

    expect(mdsSplit($markdown, 80))->toBe([
        "# Returns\n\nYou can return any item within 30 days of delivery.",
        "# Shipping\n\nWe ship in two days.\n\n# Payment\n\nWe take cards.",
    ]);
});

it('repeats the headings above a chunk from the middle of a long section', function (): void {
    $markdown = <<<'MD'
        # Pricing

        ## Enterprise

        ### Limits

        Each workspace has ten agents. Each agent has many sources.

        Each source may hold a million words. Ask us for more.
        MD;

    $chunks = mdsSplit($markdown, 100);

    expect($chunks)->toBe([
        "# Pricing\n## Enterprise\n### Limits\n\nEach workspace has ten agents. Each agent has many sources.",
        "# Pricing\n## Enterprise\n### Limits\n\nEach source may hold a million words. Ask us for more.",
    ]);

    foreach ($chunks as $chunk) {
        expect(Str::length($chunk))->toBeLessThanOrEqual(100);
    }
});

it('keeps only the closest heading when the headings would take more than half the chunk', function (): void {
    $markdown = "# A very long title for the whole help centre\n\n## Returns\n\nYou can return any item within thirty days. Keep the receipt.";

    expect(mdsSplit($markdown, 60))->toBe([
        "## Returns\n\nYou can return any item within thirty days.",
        "## Returns\n\nKeep the receipt.",
    ]);
});

it('does not take a comment inside a code block for a heading', function (): void {
    $markdown = <<<'MD'
        # Install

        ```bash
        # Download the files
        curl -O https://example.com/app.tar.gz
        ```

        Then start the app.
        MD;

    expect(mdsSplit($markdown, 200))->toBe([$markdown])
        ->and(mdsSplit($markdown, 90))->toBe([
            "# Install\n\n```bash\n# Download the files\ncurl -O https://example.com/app.tar.gz\n```",
            "# Install\n\nThen start the app.",
        ]);
});

it('finds underlined headings (= for level 1, - for level 2), and keeps a lone rule as text', function (): void {
    $markdown = <<<'MD'
        Returns
        =======

        Any item within 30 days.

        ---

        Shipping
        --------

        Two days.
        MD;

    expect(mdsSplit($markdown, 50))->toBe([
        "Returns\n=======\n\nAny item within 30 days.\n\n---",
        "# Returns\n\nShipping\n--------\n\nTwo days.",
    ]);
});

it('cuts a long code block by lines, with its fences in every part', function (): void {
    $code = implode("\n", array_map(fn (int $line): string => "echo 'line {$line}';", range(1, 12)));
    $chunks = mdsSplit("# Script\n\n```php\n{$code}\n```", 120);

    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect($chunk)->toStartWith("# Script\n\n```php\n")
            ->and($chunk)->toEndWith("\n```")
            ->and(Str::length($chunk))->toBeLessThanOrEqual(120);
    }

    expect(implode("\n", array_map(fn (string $chunk): string => Str::of($chunk)->chopStart("# Script\n\n```php\n")->chopEnd("\n```")->toString(), $chunks)))->toBe($code);
});

it('keeps a table that fits whole, and repeats the header row in every part of a long one', function (): void {
    $rows = implode("\n", array_map(fn (int $row): string => "| Shop {$row} | Street {$row} |", range(1, 10)));
    $table = "| Shop | Address |\n|---|---|\n{$rows}";

    expect(mdsSplit("# Shops\n\n{$table}\n\nAll shops close on holidays.", 400))->toBe(["# Shops\n\n{$table}\n\nAll shops close on holidays."]);

    $chunks = mdsSplit("# Shops\n\n{$table}", 120);

    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect($chunk)->toStartWith("# Shops\n\n| Shop | Address |\n|---|---|\n| Shop ")
            ->and(Str::length($chunk))->toBeLessThanOrEqual(120);
    }
});

it('leaves out a bare heading that only leads into the next section', function (): void {
    $markdown = "# Guide\n\n## Returns\n\n".Str::repeat('Any item within 30 days. ', 4);

    expect(mdsSplit($markdown, 80))->toBe([
        "# Guide\n## Returns\n\nAny item within 30 days. Any item within 30 days.",
        "# Guide\n## Returns\n\nAny item within 30 days. Any item within 30 days.",
    ]);
});

it('splits Arabic Markdown in tokens and characters without cutting a letter', function (ChunkMeasure $measure, int $size): void {
    $markdown = "# مساعدة\n\n## الإرجاع\n\n".Str::repeat('يمكنك إرجاع أي منتج خلال ثلاثين يوما. احتفظ بالإيصال. ', 6)."\n\n## الشحن\n\nنشحن خلال يومين 👍🏽.";
    $chunks = mdsSplit($markdown, $size, 0, $measure);

    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect(mb_check_encoding($chunk, 'UTF-8'))->toBeTrue()
            ->and($measure->length($chunk))->toBeLessThanOrEqual($size)
            ->and($chunk)->toStartWith('# مساعدة');
    }
})->with([
    'tokens' => [fn (): ChunkMeasure => new TokenMeasure('cl100k_base'), 80],
    'characters' => [fn (): ChunkMeasure => new CharacterMeasure, 120],
]);

it('splits a crawled page and an ebook into chunks that fit', function (string $markdown): void {
    $measure = new TokenMeasure('cl100k_base');
    $chunks = mdsSplit($markdown, 120, 0, $measure);

    expect(count($chunks))->toBeGreaterThan(2);

    foreach ($chunks as $chunk) {
        expect($measure->length($chunk))->toBeLessThanOrEqual(120)
            ->and(Str::trim($chunk))->not->toBe('');
    }
})->with([
    'help centre sample' => [fn (): string => File::get(resource_path('admin/samples/chunking/markdown.md'))],
    'Pandoc ebook' => [fn (): string => implode("\n\n", array_map(
        fn (int $chapter): string => "Chapter {$chapter}\n=========\n\n".Str::repeat("The night was cold and the road was long. She walked on, thinking of home.\n\n", 5),
        range(1, 3),
    ))],
]);

it('carries the overlap between blocks of a long section', function (): void {
    $markdown = "# Notes\n\nFirst note.\n\nSecond note.\n\nThird note.\n\nFourth note.";

    expect(mdsSplit($markdown, 45, 15))->toBe([
        "# Notes\n\nFirst note.\n\nSecond note.",
        "# Notes\n\nSecond note.\n\nThird note.",
        "# Notes\n\nThird note.\n\nFourth note.",
    ]);
});

it('evens out the short last part of a long section', function (): void {
    $markdown = "# Returns\n\nItems return free. Keep the receipt. Ask us.";

    expect(mdsSplit($markdown, 50))->toBe([
        "# Returns\n\nItems return free. Keep the receipt.",
        "# Returns\n\nAsk us.",
    ])->and(mdsSplit($markdown, 50, minimum: 15))->toBe([
        "# Returns\n\nItems return free.",
        "# Returns\n\nKeep the receipt. Ask us.",
    ]);
});
