<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Llm\Enums\AiProvider;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * An admin chooses the file types workspaces may upload as knowledge
 * (Settings → Ingestion → File types). The upload endpoint refuses the others,
 * and the dashboard gets the accepted types with their extensions.
 */
beforeEach(function (): void {
    Storage::fake();
    // PDF, Word, slides and images need a document reader.
    connectAiProviders(AiProvider::Mistral);

    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();

    $this->agent = createAgent($this->owner);
});

it('refuses an upload whose file type the admin turned off', function (): void {
    config(['chatvia.knowledge.file_types' => ['pdf']]);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.upload', $this->agent), [
            'file' => UploadedFile::fake()->createWithContent('opening-hours.txt', 'We open at nine.'),
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Unsupported file type.');

    expect(SourceFile::query()->where('workspace_id', $this->owner->workspace()->id)->exists())->toBeFalse();
});

it('accepts an upload whose file type is on', function (): void {
    config(['chatvia.knowledge.file_types' => ['text']]);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.files.upload', $this->agent), [
            'file' => UploadedFile::fake()->createWithContent('opening-hours.txt', 'We open at nine.'),
        ])
        ->assertOk();

    expect(SourceFile::query()->where('workspace_id', $this->owner->workspace()->id)->value('filetype'))->toBe('text');
});

it('shares the accepted file types with their extensions', function (): void {
    config(['chatvia.knowledge.file_types' => ['pdf', 'audio'], 'chatvia.ai.transcriber' => 'scaleway']);

    $this->actingAs($this->owner)
        ->get(route('agents.sources.files', $this->agent))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('knowledgeFileTypes.pdf', ['pdf'])
            ->has('knowledgeFileTypes.audio')
            ->missing('knowledgeFileTypes.word')
            ->missing('knowledgeFileTypes.video'));
});

it('offers no audio or video while no speech-to-text service is set up', function (): void {
    config(['chatvia.knowledge.file_types' => ['pdf', 'audio', 'video'], 'chatvia.ai.transcriber' => 'none']);

    $this->actingAs($this->owner)
        ->get(route('agents.sources.files', $this->agent))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('knowledgeFileTypes.pdf')
            ->missing('knowledgeFileTypes.audio')
            ->missing('knowledgeFileTypes.video'));
});

it('says whether websites can be imported', function (): void {
    config(['services.firecrawl.key' => null]);

    $this->actingAs($this->owner)
        ->get(route('agents.sources.files', $this->agent))
        ->assertInertia(fn (Assert $page): Assert => $page->where('canImportWebsites', false));

    config(['services.firecrawl.key' => 'fc-key']);

    $this->actingAs($this->owner)
        ->get(route('agents.sources.files', $this->agent))
        ->assertInertia(fn (Assert $page): Assert => $page->where('canImportWebsites', true));
});
