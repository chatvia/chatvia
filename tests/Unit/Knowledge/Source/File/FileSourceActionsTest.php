<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\Document\DocumentIngestionJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Actions\DeleteSourceFileFromAgentAction;
use Domain\Knowledge\Source\File\Actions\DeleteUploadedSourceFilesAction;
use Domain\Knowledge\Source\File\Actions\ProcessAgentUploadedFilesAction;
use Domain\Knowledge\Source\File\Actions\UploadKnowledgeSourceFileAction;
use Domain\Knowledge\Source\File\Data\UploadedSourceFileData;
use Domain\Knowledge\Source\File\Exceptions\UnsupportedSourceFileException;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

/**
 * File sources: uploading (no HTTP upload object in the Domain), training the
 * uploaded files, discarding them, and deleting one.
 */
beforeEach(function (): void {
    Storage::fake();
    // PDF, Word, slides and images need a document reader.
    connectAiProviders(AiProvider::Mistral);
});

function fsaUpload(string $name, string $content, string $mimeType): UploadedSourceFileData
{
    $path = tempnam(sys_get_temp_dir(), 'upl');
    file_put_contents($path, $content);

    return new UploadedSourceFileData(
        path: $path,
        originalName: $name,
        mimeType: $mimeType,
        clientMimeType: $mimeType,
        sizeInBytes: strlen($content),
    );
}

it('stores an uploaded document for the workspace and attaches it to the agent', function (): void {
    $agent = Agent::factory()->create();
    $upload = fsaUpload('cover.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgIB0AAAA0AAEjQ4N1AAAAAElFTkSuQmCC'), 'image/png');

    $file = app(UploadKnowledgeSourceFileAction::class)->execute($agent, $upload);

    expect($file->workspace_id)->toBe($agent->workspace_id)
        ->and($file->filename)->toBe('cover.png')
        ->and($file->filetype)->toBe('image')
        ->and($file->status)->toBe(SourceStatus::Uploaded)
        ->and($file->length)->toBe(1)
        ->and($file->filepath)->toStartWith('workspaces/'.$agent->workspaceExternalId().'/sources/source_file/'.$file->external_id.'/')
        ->and($agent->files()->whereKey($file->id)->exists())->toBeTrue();

    Storage::assertExists($file->filepath);
});

it('refuses a file type the knowledge base cannot read', function (): void {
    $agent = Agent::factory()->create();

    expect(fn () => app(UploadKnowledgeSourceFileAction::class)->execute($agent, fsaUpload('tool.exe', 'MZ', 'application/x-msdownload')))
        ->toThrow(UnsupportedSourceFileException::class);

    expect(SourceFile::query()->where('workspace_id', $agent->workspace_id)->exists())->toBeFalse();
});

it('refuses a file type the admin turned off', function (): void {
    config(['chatvia.knowledge.file_types' => ['pdf']]);
    $agent = Agent::factory()->create();

    expect(fn () => app(UploadKnowledgeSourceFileAction::class)->execute($agent, fsaUpload('notes.txt', 'Opening hours', 'text/plain')))
        ->toThrow(UnsupportedSourceFileException::class);

    expect(SourceFile::query()->where('workspace_id', $agent->workspace_id)->exists())->toBeFalse();
});

it('accepts audio only while the admin has audio on', function (): void {
    // Audio needs a speech-to-text service.
    config(['chatvia.ai.transcriber' => 'scaleway']);
    $upload = fsaUpload('note.mp3', 'ID3', 'audio/mpeg');

    expect($upload->isSupported())->toBeTrue()
        ->and($upload->category())->toBe('audio');

    config(['chatvia.knowledge.file_types' => ['pdf', 'video']]);

    expect($upload->isSupported())->toBeFalse();
});

it('starts training every uploaded file of the agent', function (): void {
    Bus::fake();

    $agent = Agent::factory()->create();
    $uploaded = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'filename' => 'a.pdf', 'mime_type' => 'application/pdf']);
    $trained = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'status' => 'ingested']);
    $agent->files()->attach([$uploaded->id, $trained->id]);

    app(ProcessAgentUploadedFilesAction::class)->execute($agent);

    expect($uploaded->fresh()->status)->toBe(SourceStatus::Processing)
        ->and($trained->fresh()->status)->toBe(SourceStatus::Completed);

    Bus::assertDispatchedTimes(DocumentIngestionJob::class, 1);
});

it('discards the uploaded files with their stored objects and agent links', function (): void {
    $agent = Agent::factory()->create();
    $uploaded = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $trained = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'status' => 'ingested']);
    $agent->files()->attach([$uploaded->id, $trained->id]);
    Storage::put($uploaded->filepath, 'bytes');

    expect(app(DeleteUploadedSourceFilesAction::class)->execute($agent))->toBe(1);

    expect(SourceFile::query()->find($uploaded->id))->toBeNull()
        ->and(SourceFile::query()->find($trained->id))->not->toBeNull()
        ->and($agent->files()->count())->toBe(1);

    Storage::assertMissing($uploaded->filepath);
});

it('deletes a file and cancels nothing when it is not being ingested', function (): void {
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'status' => 'ingested']);
    $agent->files()->attach($file->id);

    app(DeleteSourceFileFromAgentAction::class)->execute($agent, $file);

    expect(SourceFile::query()->find($file->id))->toBeNull();
});
