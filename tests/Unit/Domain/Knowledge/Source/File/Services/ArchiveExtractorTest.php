<?php

use Domain\Knowledge\Source\File\Data\ExtractedArchiveFileData;
use Domain\Knowledge\Source\File\Services\ArchiveExtractor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The archive extractor treats the zip as untrusted: entry names become plain
 * relative paths, every file is written under a random name, oversized
 * entries and archives nested too deep are skipped by name.
 */
function aeZip(array $entries): string
{
    $path = tempTestFile('archive.zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);

    foreach ($entries as $name => $content) {
        $zip->addFromString($name, $content);
    }

    $zip->close();

    return $path;
}

function aeFolder(): string
{
    return deleteAfterTest(create_temp_dir('/archive-tests/'.Str::uuid7()));
}

it('keeps knowledge files under plain relative names and writes them under random names', function (): void {
    $folder = aeFolder();

    $contents = new ArchiveExtractor()->extract(aeZip([
        '../../etc/passwd.txt' => 'root',
        'folder/' => '',
        'folder\\report.md' => '# Report',
        '.hidden.txt' => 'secret',
        'app.js' => 'alert(1)',
    ]), $folder);

    expect(collect($contents->files)->map(fn (ExtractedArchiveFileData $file): string => $file->name)->all())->toBe(['etc/passwd.txt', 'folder/report.md'])
        ->and($contents->skipped)->toBe(['app.js'])
        ->and(collect($contents->files)->every(fn (ExtractedArchiveFileData $file): bool => Str::startsWith($file->path, $folder.'/') && File::exists($file->path)))->toBeTrue()
        ->and(File::get($contents->files[1]->path))->toBe('# Report');
});

it('opens archives inside the archive up to the depth cap', function (): void {
    $folder = aeFolder();
    // The upload is level one; the archive five levels down holds the sixth, past the cap.
    $inner = aeZip(['6.txt' => '6']);
    foreach (range(5, 2) as $level) {
        $inner = aeZip(["{$level}.txt" => (string) $level, ($level + 1).'.zip' => File::get($inner)]);
    }
    $archive = aeZip(['1.txt' => '1', '2.zip' => File::get($inner), 'broken.zip' => 'not a zip']);

    $contents = new ArchiveExtractor()->extract($archive, $folder);

    expect(collect($contents->files)->map(fn (ExtractedArchiveFileData $file): string => $file->name)->all())
        ->toBe(['1.txt', '2.zip/2.txt', '2.zip/3.zip/3.txt', '2.zip/3.zip/4.zip/4.txt', '2.zip/3.zip/4.zip/5.zip/5.txt'])
        ->and($contents->skipped)->toBe(['2.zip/3.zip/4.zip/5.zip/6.zip', 'broken.zip'])
        ->and(File::files($folder))->toHaveCount(5);
});

it('skips an entry that inflates past the file cap', function (): void {
    $folder = aeFolder();
    $archive = aeZip(['small.txt' => 'ok', 'huge.txt' => Str::repeat('a', ArchiveExtractor::MAX_FILE_BYTES + 1)]);

    $contents = new ArchiveExtractor()->extract($archive, $folder);

    expect(collect($contents->files)->map(fn (ExtractedArchiveFileData $file): string => $file->name)->all())->toBe(['small.txt'])
        ->and($contents->skipped)->toBe(['huge.txt']);
});
