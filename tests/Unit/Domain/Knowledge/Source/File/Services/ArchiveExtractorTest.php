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
        'folder/Thumbs.db' => 'junk',
        'app.js' => 'alert(1)',
    ]), $folder);

    expect(collect($contents->files)->map(fn (ExtractedArchiveFileData $file): string => $file->name)->all())->toBe(['etc/passwd.txt', 'folder/report.md'])
        ->and($contents->skipped)->toBe(['app.js'])
        ->and(collect($contents->files)->every(fn (ExtractedArchiveFileData $file): bool => Str::startsWith($file->path, $folder.'/') && File::exists($file->path)))->toBeTrue()
        ->and(File::get($contents->files[1]->path))->toBe('# Report');
});

it('opens archives inside the archive up to the depth cap', function (): void {
    $folder = aeFolder();
    $level4 = aeZip(['four.txt' => '4']);
    $level3 = aeZip(['three.txt' => '3', 'four.zip' => File::get($level4)]);
    $level2 = aeZip(['two.txt' => '2', 'three.zip' => File::get($level3)]);
    $level1 = aeZip(['one.txt' => '1', 'two.zip' => File::get($level2), 'broken.zip' => 'not a zip']);

    $contents = new ArchiveExtractor()->extract($level1, $folder);

    expect(collect($contents->files)->map(fn (ExtractedArchiveFileData $file): string => $file->name)->all())->toBe(['one.txt', 'two.zip/two.txt', 'two.zip/three.zip/three.txt'])
        ->and($contents->skipped)->toBe(['two.zip/three.zip/four.zip', 'broken.zip'])
        ->and(File::files($folder))->toHaveCount(3);
});

it('skips an entry that inflates past the file cap', function (): void {
    $folder = aeFolder();
    $archive = aeZip(['small.txt' => 'ok', 'huge.txt' => Str::repeat('a', ArchiveExtractor::MAX_FILE_BYTES + 1)]);

    $contents = new ArchiveExtractor()->extract($archive, $folder);

    expect(collect($contents->files)->map(fn (ExtractedArchiveFileData $file): string => $file->name)->all())->toBe(['small.txt'])
        ->and($contents->skipped)->toBe(['huge.txt']);
});
