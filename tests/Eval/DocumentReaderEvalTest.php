<?php

declare(strict_types=1);

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Document\Conversion\CatDoc;
use Domain\Knowledge\Document\Conversion\Markdown\MarkdownReaders;
use Domain\Knowledge\Document\Conversion\OfficeText;
use Domain\Knowledge\Document\Conversion\Pandoc;
use Domain\Knowledge\Ingestion\Actions\Chunks\CreateSourceChunksAction;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Data\Chunking\ChunkingConfigData;
use Domain\Knowledge\Ingestion\Data\Chunking\ChunkPagesData;
use Domain\Knowledge\Ingestion\Data\Chunking\PageGroupData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Enums\ChunkEnhancement;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Services\Chunking\BuiltinTextSplitter;
use Domain\Knowledge\Retrieval\Data\RetrievalSettingsData;
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Domain\Knowledge\Retrieval\Queries\HybridChunkSearch;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\Eval\Support\MarkdownComparison;

/**
 * OFFLINE EVAL — not a pass/fail gate. Compares the built-in PHP readers
 * (Domain\Knowledge\Document\Conversion\Markdown) with the program each
 * one stands in for, on a folder of documents: pandoc for DOCX, ODT, RTF,
 * EPUB and FB2, catdoc for DOC and catppt for PPT. So the owner can decide
 * per format whether the PHP reader is good enough for hosts without the
 * program.
 *
 *     EVAL_DOCUMENTS=/path/to/samples vendor/bin/pest tests/Eval/DocumentReaderEvalTest.php
 *
 * For each file it reports how many of the program's words the PHP reader
 * kept (recall) and added (precision), whether their order holds (shared
 * three-word runs), and the headings, tables, table rows and footnotes on
 * each side, with words the PHP reader ran together or split apart. The
 * program's side is pandoc's GitHub Markdown (`gfm`, images taken out as
 * OfficeText does), or catdoc's plain text, which has no headings or tables
 * to count.
 *
 * Env (one run):
 *  - EVAL_DOCUMENTS: the sample folder, read recursively (required; skips without it)
 *  - EVAL_REPORT: where the Markdown report goes (default: the temp folder)
 *  - EVAL_OUTPUT: a folder for each file's two Markdown readings, to diff by hand
 *  - EVAL_CATDOC, EVAL_CATPPT: the catdoc and catppt to compare with, when
 *    they are not the ones in the settings (such as a wrapper that runs them
 *    in a container)
 *  - EVAL_RETRIEVAL=1: also chunk and embed both readings and ask the questions
 *    in EVAL_DOCUMENTS/questions.php (needs a real embeddings key, e.g.
 *    SCALEWAYAI_API_KEY=…, and network)
 *
 * questions.php returns questions per document name, the part of the file
 * name before the first dot ("handbook" for handbook.v2.docx):
 *
 *     return ['handbook' => [['query' => 'How many holiday days?', 'expect' => '28 days']]];
 */

/**
 * The sample files, sorted, relative to the folder.
 *
 * @return list<string>
 */
function dreFiles(string $folder): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && app(MarkdownReaders::class)->supports($file->getExtension())) {
            $files[] = substr($file->getPathname(), strlen(rtrim($folder, '/')) + 1);
        }
    }

    sort($files);

    return $files;
}

/**
 * Runs one reader and times it.
 *
 * @return array{markdown: string|null, ms: float, error: string|null}
 */
function dreRead(callable $read): array
{
    $started = hrtime(true);

    try {
        $markdown = $read();
        $error = null;
    } catch (Throwable $e) {
        $markdown = null;
        $error = $e->getMessage();
    }

    return ['markdown' => $markdown, 'ms' => (hrtime(true) - $started) / 1e6, 'error' => $error];
}

/**
 * The program's reading: catdoc's text for DOC and PPT, otherwise pandoc's
 * GitHub Markdown with image references out as OfficeText takes them out.
 */
function dreProgram(string $path, string $extension): string
{
    if (in_array($extension, ['doc', 'ppt'], true)) {
        return app(CatDoc::class)->toText($path, $extension);
    }

    $markdown = app(Pandoc::class)->toGithubMarkdown($path, $extension);

    return (string) preg_replace(['/!\[[^\]]*\]\([^)]*\)/', '/<img\b[^>]*>/i'], '', $markdown);
}

it('compares the built-in readers with the programs on the sample documents', function (): void {
    $folder = (string) env('EVAL_DOCUMENTS', '');

    if ($folder === '' || ! is_dir($folder)) {
        $this->markTestSkipped('needs a sample folder: EVAL_DOCUMENTS=/path/to/samples vendor/bin/pest tests/Eval/DocumentReaderEvalTest.php');
    }

    if (! programInstalled('pandoc')) {
        $this->markTestSkipped('pandoc is not installed, so there is nothing to compare with.');
    }

    foreach (['catdoc' => 'EVAL_CATDOC', 'catppt' => 'EVAL_CATPPT'] as $program => $variable) {
        if (filled(env($variable))) {
            config(["services.bin.{$program}" => env($variable)]);
        }
    }

    $output = (string) env('EVAL_OUTPUT', '');

    if ($output !== '') {
        File::ensureDirectoryExists($output);
    }

    $rows = [];

    foreach (dreFiles($folder) as $file) {
        $path = rtrim($folder, '/').'/'.$file;
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        $program = dreRead(fn (): string => dreProgram($path, $extension));
        $php = dreRead(fn (): string => app(MarkdownReaders::class)->toMarkdown($path, $extension));

        if ($output !== '') {
            $base = $output.'/'.str_replace('/', '__', $file);
            File::put($base.'.program.md', $program['markdown'] ?? 'ERROR: '.$program['error']);
            File::put($base.'.php.md', $php['markdown'] ?? 'ERROR: '.$php['error']);
        }

        $rows[] = [
            'file' => $file,
            'format' => $extension,
            'program' => $program,
            'php' => $php,
            'metrics' => $program['markdown'] !== null && $php['markdown'] !== null
                ? new MarkdownComparison($program['markdown'], $php['markdown'])->metrics()
                : null,
        ];
    }

    $report = dreReport($rows, $folder);

    if (env('EVAL_RETRIEVAL') && is_file($folder.'/questions.php')) {
        $report .= "\n".dreRetrieval($folder, require $folder.'/questions.php');
    }

    $target = (string) env('EVAL_REPORT', sys_get_temp_dir().'/document-reader-eval.md');
    File::put($target, $report);

    fwrite(STDOUT, PHP_EOL.dreSummary($rows).PHP_EOL.'Full report: '.$target.PHP_EOL);

    expect($rows)->not->toBeEmpty();
})->group('eval');

/**
 * The per-format summary: averages over the files read by both.
 *
 * @param  list<array<string, mixed>>  $rows
 */
function dreSummary(array $rows): string
{
    $lines = ['| Format | Files | Recall | Precision | Word order | Headings program → PHP | Tables program → PHP | Rows program → PHP | Footnotes program → PHP | Run together (PHP / program) | Errors (program / PHP) | ms program / PHP |', '|---|---|---|---|---|---|---|---|---|---|---|---|'];

    foreach (['docx', 'odt', 'rtf', 'doc', 'ppt', 'epub', 'fb2'] as $format) {
        $set = array_values(array_filter($rows, static fn (array $row): bool => $row['format'] === $format));
        $measured = array_values(array_filter(array_column($set, 'metrics')));

        if ($set === []) {
            continue;
        }

        $mean = static fn (string $key): string => $measured === [] ? 'n/a' : sprintf('%.3f', array_sum(array_column($measured, $key)) / count($measured));
        $sum = static fn (string $key, int $side): int => array_sum(array_map(static fn (array $metrics): int => $metrics[$key][$side], $measured));
        $count = static fn (string $key): int => array_sum(array_map(static fn (array $metrics): int => count($metrics[$key]), $measured));

        $lines[] = sprintf('| %s | %d | %s | %s | %s | %d → %d | %d → %d | %d → %d | %d → %d | %d / %d | %d / %d | %.0f / %.0f |',
            $format, count($set), $mean('recall'), $mean('precision'), $mean('order'),
            $sum('headings', 0), $sum('headings', 1), $sum('tables', 0), $sum('tables', 1), $sum('rows', 0), $sum('rows', 1),
            $sum('footnotes', 0), $sum('footnotes', 1), $count('run_together'), $count('split_apart'),
            count(array_filter($set, static fn (array $row): bool => $row['program']['error'] !== null)),
            count(array_filter($set, static fn (array $row): bool => $row['php']['error'] !== null)),
            array_sum(array_map(static fn (array $row): float => $row['program']['ms'], $set)),
            array_sum(array_map(static fn (array $row): float => $row['php']['ms'], $set)));
    }

    return implode(PHP_EOL, $lines);
}

/**
 * The full Markdown report: the summary, one row per file, then the details
 * of every difference.
 *
 * @param  list<array<string, mixed>>  $rows
 */
function dreReport(array $rows, string $folder): string
{
    $out = ['# Document readers: programs vs PHP', '', 'Samples: `'.$folder.'` · '.count($rows).' files · '.Str::before(trim(Process::run([(string) config('services.bin.pandoc'), '--version'])->output()), "\n").' · catdoc and catppt for DOC and PPT', ''];
    $out[] = '## Summary per format';
    $out[] = '';
    $out[] = dreSummary($rows);
    $out[] = '';
    $out[] = 'The program is pandoc, or catdoc and catppt for DOC and PPT. Recall: share of the program\'s words the PHP reader kept. Precision: share of the PHP reader\'s words the program also has. Word order: shared three-word runs (1.0 = same order). Run together: words the PHP reader joined that the program kept apart; the second number is the reverse (the program joined them).';
    $out[] = '';
    $out[] = '## Per file';
    $out[] = '';
    $out[] = '| File | Words program / PHP | Recall | Precision | Order | Headings | Tables | Rows | Footnotes | Run together | ms program / PHP |';
    $out[] = '|---|---|---|---|---|---|---|---|---|---|---|';

    foreach ($rows as $row) {
        $metrics = $row['metrics'];

        if ($metrics === null) {
            $out[] = sprintf('| %s | error: %s | | | | | | | | | |', $row['file'], str_replace('|', '/', (string) ($row['program']['error'] ?? $row['php']['error'])));

            continue;
        }

        $out[] = sprintf('| %s | %d / %d | %.3f | %.3f | %.3f | %d → %d | %d → %d | %d → %d | %d → %d | %d / %d | %.0f / %.0f |',
            $row['file'], $metrics['words'][0], $metrics['words'][1], $metrics['recall'], $metrics['precision'], $metrics['order'],
            $metrics['headings'][0], $metrics['headings'][1], $metrics['tables'][0], $metrics['tables'][1],
            $metrics['rows'][0], $metrics['rows'][1], $metrics['footnotes'][0], $metrics['footnotes'][1],
            count($metrics['run_together']), count($metrics['split_apart']), $row['program']['ms'], $row['php']['ms']);
    }

    $out[] = '';
    $out[] = '## Differences';

    foreach ($rows as $row) {
        $metrics = $row['metrics'];
        $notes = [];

        if ($metrics === null) {
            continue;
        }

        $list = static fn (array $items): string => implode(', ', array_map(static fn (string $item): string => '`'.$item.'`', array_slice(array_unique($items), 0, 10)));

        foreach ([
            'headings_lost' => 'Headings only the program found',
            'headings_extra' => 'Headings only PHP found',
            'run_together' => 'Run together by PHP',
            'split_apart' => 'Run together by the program',
            'missing' => 'Words only the program has',
            'extra' => 'Words only PHP has',
        ] as $key => $label) {
            if ($metrics[$key] !== []) {
                $notes[] = '- '.$label.' ('.count($metrics[$key]).'): '.$list($metrics[$key]);
            }
        }

        if ($notes !== []) {
            $out[] = '';
            $out[] = '### '.$row['file'];
            $out[] = '';
            array_push($out, ...$notes);
        }
    }

    return implode("\n", $out)."\n";
}

/**
 * Chunks a reading the way an upload is chunked: office text cut into pages
 * of about OfficeText::CHARS_PER_PAGE characters (a long page split as text),
 * ebooks split as Markdown.
 *
 * @return list<PageData>
 */
function dreChunks(string $markdown, string $format): array
{
    $splitter = app(TextSplitter::class);

    if (in_array($format, ['epub', 'fb2'], true)) {
        return PageData::fromParts($splitter->split($markdown, ContentKind::Markdown));
    }

    $config = ChunkingConfigData::forKind(ContentKind::Text);
    $pages = [];
    $current = '';

    foreach (preg_split("/\n{2,}/", $markdown) ?: [] as $paragraph) {
        $current = $current === '' ? $paragraph : $current."\n\n".$paragraph;

        if (mb_strlen($current) >= OfficeText::CHARS_PER_PAGE) {
            $pages[] = $current;
            $current = '';
        }
    }

    if ($current !== '') {
        $pages[] = $current;
    }

    $chunks = [];

    foreach (PageData::fromParts($pages) as $page) {
        array_push($chunks, ...($config->measure()->length($page->content) > $config->size
            ? $page->partsOf($splitter->split($page->content, ContentKind::Text))
            : [$page]));
    }

    return $chunks;
}

/**
 * Retrieval with each reader's chunks: for every sample file that has
 * questions, an agent per reader holds that file's chunks (embedded for
 * real), and each question is searched with the hybrid search. Reports
 * hit@1, hit@3 and MRR before the relevance check, per format and reader.
 *
 * The program's side is what uploads use with it installed: `gfm` for
 * DOCX/ODT/RTF and catdoc's text for DOC/PPT (OfficeText), pandoc's own
 * Markdown for ebooks (IngestTextFileAction).
 *
 * @param  array<string, list<array{query: string, expect: string}>>  $questions
 */
function dreRetrieval(string $folder, array $questions): string
{
    try {
        $embeddings = evalUseRealModels();
    } catch (Throwable $e) {
        return "## Retrieval\n\nSkipped: real embeddings unavailable ({$e->getMessage()}).\n";
    }

    app()->instance(TextSplitter::class, new BuiltinTextSplitter);

    $settings = RetrievalSettingsData::fromConfig();
    $search = app(HybridChunkSearch::class);
    $vectors = [];
    $ranks = [];
    $misses = [];

    foreach (dreFiles($folder) as $file) {
        $cases = $questions[explode('.', basename($file))[0]] ?? [];
        $path = rtrim($folder, '/').'/'.$file;
        $format = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if ($cases === []) {
            continue;
        }

        $readings = [
            'program' => dreRead(fn (): string => in_array($format, ['epub', 'fb2'], true)
                ? app(Pandoc::class)->toMarkdown($path, $format)
                : dreProgram($path, $format)),
            'php' => dreRead(fn (): string => app(MarkdownReaders::class)->toMarkdown($path, $format)),
        ];

        foreach ($readings as $reader => $reading) {
            $agent = Agent::factory()->create();
            $text = (string) $reading['markdown'];
            $source = SourceText::factory()->ingested()->attachedTo($agent)->create([
                'excerpt' => mb_substr($text, 0, 200),
                'length' => mb_strlen($text),
                'checksum' => md5($reader.$file.$text),
            ]);

            app(CreateSourceChunksAction::class)->execute(new ChunkPagesData(
                source: $source,
                group: new PageGroupData(dreChunks($text, $format)),
                enhancement: ChunkEnhancement::Off,
            ));

            foreach ($cases as $case) {
                $vector = $vectors[$case['query']] ??= '['.implode(',', $embeddings->embedQuery($case['query'])).']';
                $scope = $search->scope($agent, $settings->exactMaxChunks);
                $candidates = $search->search($agent, $case['query'], $vector, 10, $scope, $settings->accuracy);
                $rank = dreRank($candidates, $case['expect']);

                $ranks[$format][$reader][] = $rank;

                if ($rank === null || $rank > 3) {
                    $misses[] = sprintf('- %s, %s: "%s" (%s)', $file, $reader, $case['query'], $rank === null ? 'not found' : "rank {$rank}");
                }
            }
        }
    }

    $out = ['## Retrieval', '', 'Hybrid search over each file\'s own chunks, before the relevance check. A question counts when a chunk holding its expected text comes back.', '', '| Format | Reader | Questions | hit@1 | hit@3 | MRR |', '|---|---|---|---|---|---|'];

    foreach ($ranks as $format => $readers) {
        foreach ($readers as $reader => $list) {
            $hit = static fn (int $k): string => sprintf('%.1f%%', 100 * count(array_filter($list, static fn (?int $rank): bool => $rank !== null && $rank <= $k)) / count($list));
            $mrr = array_sum(array_map(static fn (?int $rank): float => $rank === null ? 0.0 : 1 / $rank, $list)) / count($list);
            $out[] = sprintf('| %s | %s | %d | %s | %s | %.3f |', $format, $reader, count($list), $hit(1), $hit(3), $mrr);
        }
    }

    $out[] = '';
    $out[] = $misses === [] ? 'No misses.' : "Misses (not in the top 3):\n\n".implode("\n", $misses);

    return implode("\n", $out)."\n";
}

/**
 * The 1-based rank of the first chunk holding the expected text, spaces and
 * Markdown escapes ignored; null when none does.
 *
 * @param  list<RetrievedChunkData>  $chunks
 */
function dreRank(array $chunks, string $expect): ?int
{
    $plain = static fn (string $text): string => (string) preg_replace('/\s+/u', ' ', str_replace('\\', '', $text));

    foreach ($chunks as $index => $chunk) {
        if (mb_stripos($plain($chunk->chunk), $plain($expect)) !== false) {
            return $index + 1;
        }
    }

    return null;
}
