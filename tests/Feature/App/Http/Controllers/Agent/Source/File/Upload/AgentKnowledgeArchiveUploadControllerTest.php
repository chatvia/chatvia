<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * POST agents.sources.files.upload-archive unpacks a zip, folders and nested
 * archives included, stores every file of a type the admin turned on as its
 * own source file, and names the files it left out.
 */
beforeEach(function (): void {
    Storage::fake();
    connectAiProviders(AiProvider::Mistral);
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

/** A zip on disk with these entries (name → content); a ZipArchive value is a nested zip's path. */
function akauZip(array $entries): string
{
    $path = tempTestFile('upload.zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);

    foreach ($entries as $name => $content) {
        $zip->addFromString($name, $content);
    }

    $zip->close();

    return $path;
}

it('stores the supported files of the archive, nested folders and archives included, and names the rest', function (): void {
    config(['chatvia.knowledge.file_types' => ['text']]);
    $inner = akauZip(['deep/notes.md' => '# Notes', 'tool.exe' => 'MZ']);
    $archive = akauZip([
        'hours.txt' => 'We open at nine.',
        'policies/refunds.txt' => 'Refunds within 30 days.',
        'policies/pricing.pdf' => '%PDF-1.4',
        'archive.zip' => File::get($inner),
        '__MACOSX/._hours.txt' => 'junk',
    ]);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.upload-archive', $this->agent), [
            'file' => new UploadedFile($archive, 'upload.zip', 'application/zip', null, true),
        ])
        ->assertCreated()
        ->assertJsonPath('uploaded', 3)
        ->assertJsonPath('length', 3)
        ->assertJsonPath('skipped', ['archive.zip/tool.exe', 'policies/pricing.pdf']);

    $files = SourceFile::query()->where('workspace_id', $this->owner->workspace()->id)->orderBy('id')->get();

    expect($files->pluck('filename')->all())->toBe(['hours.txt', 'policies/refunds.txt', 'archive.zip/deep/notes.md'])
        ->and($files->pluck('filetype')->unique()->all())->toBe([KnowledgeFileType::Text])
        ->and($this->agent->files()->count())->toBe(3);

    $files->each(fn (SourceFile $file) => Storage::assertExists($file->filepath));
});

it('skips a file the workspace already uploaded and refuses an upload that is not a zip', function (): void {
    config(['chatvia.knowledge.file_types' => ['text']]);
    $this->actingAs($this->owner)->postJson(route('agents.sources.files.upload', $this->agent), [
        'file' => UploadedFile::fake()->createWithContent('hours.txt', 'We open at nine.'),
    ])->assertOk();

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.upload-archive', $this->agent), [
            'file' => new UploadedFile(akauZip(['hours.txt' => 'We open at nine.', 'copy.txt' => 'We open at nine.', 'new.txt' => 'Closed on Sundays.']), 'upload.zip', 'application/zip', null, true),
        ])
        ->assertCreated()
        ->assertJsonPath('uploaded', 1)
        ->assertJsonPath('skipped', ['hours.txt', 'copy.txt']);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.upload-archive', $this->agent), [
            'file' => UploadedFile::fake()->createWithContent('hours.txt', 'We open at nine.'),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');

    expect(SourceFile::query()->where('workspace_id', $this->owner->workspace()->id)->count())->toBe(2);
});
