<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Actions\UploadKnowledgeSourceFileAction;
use Domain\Knowledge\Source\File\Data\UploadedSourceFileData;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Domain\Knowledge\Source\File\Exceptions\UnsupportedSourceFileException;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Knowledge\Document\PngImage;

/**
 * Uploading a knowledge file (no HTTP upload object in the Domain): the file
 * is stored in its own folder of the workspace and linked to the agent. A
 * type the knowledge base cannot read, or one the admin turned off, is
 * refused before anything is stored.
 */
beforeEach(function (): void {
    Storage::fake();
    // PDF, Word, slides and images need a document reader.
    connectAiProviders(AiProvider::Mistral);
});

function uksfUpload(string $name, string $content, string $mimeType): UploadedSourceFileData
{
    return new UploadedSourceFileData(
        path: tempTestFile($name, $content),
        originalName: $name,
        mimeType: $mimeType,
        clientMimeType: $mimeType,
        sizeInBytes: strlen($content),
    );
}

it('stores an uploaded document for the workspace and attaches it to the agent', function (): void {
    $agent = Agent::factory()->create();

    $file = app(UploadKnowledgeSourceFileAction::class)->execute($agent, uksfUpload('cover.png', PngImage::bytes(), 'image/png'));

    expect($file->workspace_id)->toBe($agent->workspace_id)
        ->and($file->filename)->toBe('cover.png')
        ->and($file->filetype)->toBe(KnowledgeFileType::Image)
        ->and($file->status)->toBe(SourceStatus::Uploaded)
        ->and($file->length)->toBe(1)
        ->and($file->filepath)->toStartWith('workspaces/'.$agent->workspaceExternalId().'/sources/source_file/'.$file->external_id.'/')
        ->and($agent->files()->whereKey($file->id)->exists())->toBeTrue();

    Storage::assertExists($file->filepath);
});

it('stores a text file holding HTML under its own extension, never as HTML', function (): void {
    $agent = Agent::factory()->create();

    $file = app(UploadKnowledgeSourceFileAction::class)->execute(
        $agent,
        uksfUpload('notes.txt', '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>', 'text/html'),
    );

    expect($file->filepath)->toEndWith('.txt');
});

it('refuses a file type the knowledge base cannot read', function (): void {
    $agent = Agent::factory()->create();

    expect(fn () => app(UploadKnowledgeSourceFileAction::class)->execute($agent, uksfUpload('tool.exe', 'MZ', 'application/x-msdownload')))
        ->toThrow(UnsupportedSourceFileException::class);

    expect(SourceFile::query()->where('workspace_id', $agent->workspace_id)->exists())->toBeFalse();
});

it('refuses a file type the admin turned off', function (): void {
    config(['chatvia.knowledge.file_types' => ['pdf']]);
    $agent = Agent::factory()->create();

    expect(fn () => app(UploadKnowledgeSourceFileAction::class)->execute($agent, uksfUpload('notes.txt', 'Opening hours', 'text/plain')))
        ->toThrow(UnsupportedSourceFileException::class);

    expect(SourceFile::query()->where('workspace_id', $agent->workspace_id)->exists())->toBeFalse();
});
