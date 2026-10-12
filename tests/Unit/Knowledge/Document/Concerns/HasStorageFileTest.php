<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Document\Concerns\HasStorageFile;
use Domain\Knowledge\Document\Conversion\OfficePdf;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * HasStorageFile copies stored files to local temp files for tools that need a
 * path (OCR, Pandoc, OfficePdf). It must read the field it was asked for,
 * leave no temp files behind, and never delete a stored original.
 */
function storageFileModel(array $attributes): Model
{
    $model = new #[Unguarded] class extends Model
    {
        use HasStorageFile;
    };

    $model->forceFill($attributes);

    return $model;
}

beforeEach(function (): void {
    Storage::fake();
});

it('reads the requested field even when filepath is empty', function (): void {
    Storage::put('users/u/audio/transcript.txt', 'hello transcript');
    $model = storageFileModel(['id' => 7, 'filepath' => null, 'parser_filepath' => 'users/u/audio/transcript.txt']);

    expect($model->readContents('parser_filepath'))->toBe('hello transcript')
        ->and($model->withLocalFile(fn ($file): string => $file->getContent(), 'parser_filepath'))->toBe('hello transcript');
});

it('names the missing field instead of always blaming filepath', function (): void {
    $model = storageFileModel(['id' => 7, 'filepath' => 'users/u/a.txt', 'parser_filepath' => null]);

    expect(fn () => $model->readFile('parser_filepath'))
        ->toThrow(RuntimeException::class, 'parser_filepath');
});

it('creates exactly one local copy, with the stored extension', function (): void {
    Storage::put('users/u/doc.docx', 'docx-bytes');
    $model = storageFileModel(['id' => 1, 'filepath' => 'users/u/doc.docx']);

    $file = $model->readFile();

    try {
        // tempnam() + a second ".docx" write used to leave an empty sibling
        // file without the extension behind on every call.
        expect(file_exists(substr($file->getPathname(), 0, -strlen('.docx'))))->toBeFalse()
            ->and($file->getExtension())->toBe('docx')
            ->and($file->getContent())->toBe('docx-bytes');
    } finally {
        @unlink($file->getPathname());
    }
});

it('deletes the local copy after withLocalFile, even when the callback throws', function (): void {
    Storage::put('users/u/doc.txt', 'text');
    $model = storageFileModel(['id' => 1, 'filepath' => 'users/u/doc.txt']);

    $path = $model->withLocalFile(fn ($file): string => $file->getPathname());
    expect(file_exists($path))->toBeFalse();

    $thrownPath = null;
    try {
        $model->withLocalFile(function ($file) use (&$thrownPath): void {
            $thrownPath = $file->getPathname();

            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    expect($thrownPath)->not->toBeNull()
        ->and(file_exists($thrownPath))->toBeFalse();
});

it('hands a stored PDF to withPdf as-is and never deletes it', function (): void {
    Storage::put('users/u/report.pdf', '%PDF-1.7');
    $model = storageFileModel(['id' => 1, 'user_id' => 1, 'filepath' => 'users/u/report.pdf']);

    $office = Mockery::mock(OfficePdf::class);
    $office->shouldNotReceive('toPdf');
    $office->shouldNotReceive('fromPages');

    $seen = $model->withPdf($office, fn (string $path): string => $path);

    expect($seen)->toBe('users/u/report.pdf');
    Storage::assertExists('users/u/report.pdf');
});

it('converts other files to a temporary PDF and cleans up every copy afterwards', function (): void {
    $owner = User::factory()->create();
    Storage::put('users/u/notes.docx', 'docx-bytes');
    $model = storageFileModel(['id' => 1, 'user_id' => $owner->id, 'filepath' => 'users/u/notes.docx']);

    $converted = sys_get_temp_dir().'/converted_'.uniqid().'.pdf';
    $inputSeen = null;

    $office = Mockery::mock(OfficePdf::class);
    $office->shouldReceive('toPdf')->once()->andReturnUsing(function (string $input, string $extension) use ($converted, &$inputSeen): string {
        expect($extension)->toBe('docx');
        $inputSeen = $input;
        file_put_contents($converted, '%PDF-converted');

        return $converted;
    });

    $pdfPath = $model->withPdf($office, function (string $path): string {
        Storage::assertExists($path);
        expect(Storage::get($path))->toBe('%PDF-converted');

        return $path;
    });

    expect($pdfPath)->toStartWith('users/'.$owner->external_id.'/tmp/')
        ->and(file_exists($converted))->toBeFalse()
        ->and(file_exists((string) $inputSeen))->toBeFalse();
    Storage::assertMissing($pdfPath);
    Storage::assertExists('users/u/notes.docx');
});

it('makes the PDF from the parsed pages, not the raw JSON, when a source has them', function (): void {
    $owner = User::factory()->create();
    Storage::put('users/u/scan.png', 'png-bytes');
    Storage::put('users/u/pages.json', json_encode([
        ['pageNumber' => 1, 'content' => 'First page text.', 'checksum' => 'a'],
        ['pageNumber' => 2, 'content' => 'Second page text.', 'checksum' => 'b'],
    ]));
    $model = storageFileModel(['id' => 1, 'user_id' => $owner->id, 'filepath' => 'users/u/scan.png', 'parser_filepath' => 'users/u/pages.json']);

    $converted = sys_get_temp_dir().'/converted_'.uniqid().'.pdf';

    $office = Mockery::mock(OfficePdf::class);
    $office->shouldNotReceive('toPdf');
    $office->shouldReceive('fromPages')->once()->with(['First page text.', 'Second page text.'])->andReturnUsing(function () use ($converted): string {
        file_put_contents($converted, '%PDF-pages');

        return $converted;
    });

    $model->withPdf($office, fn (string $path): string => $path);

    expect(file_exists($converted))->toBeFalse();
});
