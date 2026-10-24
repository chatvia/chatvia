<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Domain\Llm\Enums\AiProvider;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The Files page (GET agents.sources.files) gets the file types the admin
 * turned on, each with the extensions this server can read, and whether
 * websites can be imported. Audio and video need a speech-to-text service;
 * documents and ebooks need no program, as the app reads them itself.
 */
beforeEach(function (): void {
    // PDF, Word, slides and images need a document reader.
    connectAiProviders(AiProvider::Mistral);

    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

it('shares the accepted file types with their extensions', function (): void {
    config(['chatvia.knowledge.file_types' => ['pdf', 'audio'], 'chatvia.ai.transcriber' => 'scaleway']);

    $this->actingAs($this->owner)
        ->get(route('agents.sources.files', $this->agent))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('knowledgeFileTypes.pdf', ['pdf'])
            ->where('knowledgeFileTypes.audio', KnowledgeFileType::Audio->extensions())
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

it('says whether websites can be imported', function (?string $key, bool $canImport): void {
    config(['services.firecrawl.key' => $key]);

    $this->actingAs($this->owner)
        ->get(route('agents.sources.files', $this->agent))
        ->assertInertia(fn (Assert $page): Assert => $page->where('canImportWebsites', $canImport));
})->with([
    'without a FireCrawl key' => [null, false],
    'with a FireCrawl key' => ['fc-key', true],
]);

it('offers old Word, RTF and PowerPoint files on a server without catdoc, catppt or pandoc', function (): void {
    config([
        'chatvia.knowledge.file_types' => ['word', 'presentation'],
        'services.bin.catdoc' => '/nowhere/catdoc',
        'services.bin.catppt' => '/nowhere/catppt',
        'services.bin.pandoc' => '/nowhere/pandoc',
    ]);

    // The app reads them itself; Mistral OCR reads .docx and .pptx.
    $this->actingAs($this->owner)
        ->get(route('agents.sources.files', $this->agent))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('knowledgeFileTypes.word', ['doc', 'docx', 'rtf'])
            ->where('knowledgeFileTypes.presentation', ['ppt', 'pptx']));
});

it('offers ebooks on a server without pandoc, since the app reads them itself', function (): void {
    config(['chatvia.knowledge.file_types' => ['ebook'], 'services.bin.pandoc' => '/nowhere/pandoc']);

    $this->actingAs($this->owner)
        ->get(route('agents.sources.files', $this->agent))
        ->assertInertia(fn (Assert $page): Assert => $page->where('knowledgeFileTypes.ebook', ['epub', 'fb2']));
});
