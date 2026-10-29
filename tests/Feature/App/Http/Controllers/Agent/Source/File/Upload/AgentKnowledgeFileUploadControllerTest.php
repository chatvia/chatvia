<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * POST agents.sources.files.upload takes a knowledge file of a type the admin
 * turned on (Settings → Ingestion → File types) and refuses the others and a
 * file the workspace already uploaded.
 */
beforeEach(function (): void {
    Storage::fake();
    connectAiProviders(AiProvider::Mistral);
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

it('takes an upload whose file type is on and refuses the same file again', function (): void {
    config(['chatvia.knowledge.file_types' => ['text']]);
    $upload = fn () => $this->actingAs($this->owner)->postJson(route('agents.sources.files.upload', $this->agent), [
        'file' => UploadedFile::fake()->createWithContent('opening-hours.txt', 'We open at nine.'),
    ]);

    $upload()->assertOk()->assertJsonPath('length', 1);
    $upload()->assertStatus(422)->assertJsonValidationErrors('file');

    expect(SourceFile::query()->where('workspace_id', $this->owner->workspace()->id)->sole()->filetype)->toBe(KnowledgeFileType::Text);
});

it('refuses an upload whose file type the admin turned off', function (): void {
    config(['chatvia.knowledge.file_types' => ['pdf']]);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.upload', $this->agent), [
            'file' => UploadedFile::fake()->createWithContent('opening-hours.txt', 'We open at nine.'),
        ])
        ->assertStatus(422);

    expect(SourceFile::query()->where('workspace_id', $this->owner->workspace()->id)->exists())->toBeFalse();
});
