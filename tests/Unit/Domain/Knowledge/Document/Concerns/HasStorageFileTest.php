<?php

use Domain\Knowledge\Document\Concerns\HasStorageFile;
use Domain\Knowledge\Document\Conversion\OfficePdf;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File as LocalFiles;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;

/**
 * HasStorageFile copies stored files to local temp files for tools that need a
 * path (OCR, Pandoc, OfficePdf). It must leave no temp files behind, keep a
 * converted PDF under the file's own workspace root, and never delete a
 * stored original.
 */
function hsfModel(array $attributes): Model
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

it('deletes the local copy after withLocalFile, even when the callback throws', function (): void {
    Storage::put('users/u/doc.txt', 'text');
    $model = hsfModel(['id' => 1, 'filepath' => 'users/u/doc.txt']);

    $path = $model->withLocalFile(fn (File $file): string => $file->getPathname());
    $thrownPath = null;
    try {
        $model->withLocalFile(function (File $file) use (&$thrownPath): void {
            $thrownPath = $file->getPathname();

            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    expect(LocalFiles::exists($path))->toBeFalse()
        ->and($thrownPath)->not->toBeNull()
        ->and(LocalFiles::exists($thrownPath))->toBeFalse();
});

it('converts other files to a temporary PDF under the file\'s own root and cleans up every copy afterwards', function (): void {
    $root = 'workspaces/'.Str::uuid7()->toString();
    Storage::put($root.'/agents/a/files/notes.docx', 'docx-bytes');
    $model = hsfModel(['id' => 1, 'filepath' => $root.'/agents/a/files/notes.docx']);
    $converted = create_temp_dir().'/converted_'.Str::random().'.pdf';
    $inputSeen = null;
    $office = Mockery::mock(OfficePdf::class);
    $office->shouldReceive('toPdf')->once()->andReturnUsing(function (string $input, string $extension) use ($converted, &$inputSeen): string {
        expect($extension)->toBe('docx');
        $inputSeen = $input;
        LocalFiles::put($converted, '%PDF-converted');

        return $converted;
    });

    $pdfPath = $model->withPdf($office, function (string $path): string {
        Storage::assertExists($path);
        expect(Storage::get($path))->toBe('%PDF-converted');

        return $path;
    });

    expect($pdfPath)->toStartWith($root.'/tmp/')
        ->and(LocalFiles::exists($converted))->toBeFalse()
        ->and(LocalFiles::exists((string) $inputSeen))->toBeFalse();
    Storage::assertMissing($pdfPath);
    Storage::assertExists($root.'/agents/a/files/notes.docx');
});
