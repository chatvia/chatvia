<?php

use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\File\Services\SourceFileTypes;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Api\ActsAsApiOwner;
use Tests\Support\Knowledge\Document\PngImage;

/**
 * An agent's file sources through the public API: an upload answers 201
 * with the file's public id, a file of a type the agent cannot read is the
 * standard field error, and a delete takes the public id.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    Storage::fake();
    Bus::fake();
    $this->actAsApiOwner();
    $this->uri = "/api/v1/agents/{$this->agent->external_id}/sources/files";
});

it('adds a file and answers 201 with its public id', function (): void {
    connectAiProviders(AiProvider::Mistral);

    $response = $this->withToken($this->token)
        ->post($this->uri, ['file' => UploadedFile::fake()->createWithContent('cover.png', PngImage::bytes())])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'file')
        ->assertJsonPath('data.name', 'cover.png');

    $file = SourceFile::query()->where('external_id', $response->json('data.id'))->firstOrFail();

    expect($this->agent->files()->whereKey($file->id)->exists())->toBeTrue();
});

it('answers an unsupported file with the standard field error and stores nothing', function (): void {
    $this->withToken($this->token)
        ->post($this->uri, ['file' => UploadedFile::fake()->createWithContent('tool.exe', 'MZ')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file' => 'Unsupported file type.']);

    expect(SourceFile::query()->where('workspace_id', $this->agent->workspace_id)->exists())->toBeFalse();
});

it('refuses a file larger than the parser takes, before reading it', function (): void {
    $this->withToken($this->token)
        ->post($this->uri, ['file' => UploadedFile::fake()->create('handbook.txt', SourceFileTypes::MAX_UPLOAD_KB + 1)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);

    expect(SourceFile::query()->where('workspace_id', $this->agent->workspace_id)->exists())->toBeFalse();
});

it('deletes a file by its public id', function (): void {
    $file = AgentFileFactory::new()->create(['workspace_id' => $this->agent->workspace_id]);
    $this->agent->files()->attach($file->id);

    $this->withToken($this->token)->deleteJson("{$this->uri}/{$file->external_id}")->assertNoContent();

    expect($this->agent->files()->exists())->toBeFalse();
});
