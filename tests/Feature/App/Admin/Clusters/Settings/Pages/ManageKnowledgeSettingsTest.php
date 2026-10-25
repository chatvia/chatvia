<?php

use App\Admin\Clusters\Settings\Pages\ManageKnowledgeSettings;
use App\Admin\Settings\AiProviderSettings;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\KnowledgeSettings;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;

/**
 * The Knowledge page of the admin panel: what agents learn from (splitting
 * and search have their own pages). On the File types card every type is on
 * by default, an admin turns some off, and at least one stays on. Under each
 * type it says what reads it now; a type nothing can read is greyed out,
 * and a saved one stays saved. The web scraper needs a Firecrawl key.
 */
beforeEach(function (): void {
    $this->admin = adminUser();

    // A document reader and speech to text, so every type can be read.
    saveSettings(AiProviderSettings::class, ['mistral_api_key' => 'mistral-test', 'scaleway_api_key' => 'scw-test']);
    saveSettings(AiSettings::class, ['document_parser' => 'mistral', 'transcriber' => 'scaleway']);
});

it('shows every file type turned on by default', function (): void {
    adminPage(ManageKnowledgeSettings::class, $this->admin)
        ->assertSee('All file types')
        ->assertSchemaStateSet(['file_types' => array_column(KnowledgeFileType::cases(), 'value')]);
});

it('saves the file types the admin keeps on, in the order of the list', function (): void {
    adminPage(ManageKnowledgeSettings::class, $this->admin)
        ->fillForm(['file_types' => ['video', 'pdf', 'word']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(KnowledgeSettings::class, 'file_types'))->toBe(['pdf', 'word', 'video'])
        ->and(config('chatvia.knowledge.file_types'))->toBe(['pdf', 'word', 'video']);

    adminPage(ManageKnowledgeSettings::class, $this->admin)->assertSee('PDF, Word, Video');
});

it('keeps the file types unsaved while every type stays on', function (): void {
    adminPage(ManageKnowledgeSettings::class, $this->admin)
        ->fillForm(['file_types' => array_reverse(array_column(KnowledgeFileType::cases(), 'value'))])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(KnowledgeSettings::class, 'file_types'))->toBeNull();
});

it('needs at least one file type', function (): void {
    adminPage(ManageKnowledgeSettings::class, $this->admin)
        ->fillForm(['file_types' => []])
        ->call('save')
        ->assertHasFormErrors(['file_types' => 'required']);

    expect(savedSetting(KnowledgeSettings::class, 'file_types'))->toBeNull();
});

it('says what reads each file type with Mistral OCR', function (): void {
    adminPage(ManageKnowledgeSettings::class, $this->admin)
        ->assertSee([
            'Read by Mistral OCR',
            '.doc, .rtf by the app · .docx by Mistral OCR',
            '.ppt by the app · .pptx by Mistral OCR',
            'Turned into text by Scaleway AI',
        ]);
});

it('says what reads each file type with Azure', function (): void {
    saveSettings(AiProviderSettings::class, ['mistral_api_key' => null, 'azure_api_key' => 'az-key', 'azure_endpoint' => 'https://docs.cognitiveservices.azure.com']);
    saveSettings(AiSettings::class, ['document_parser' => 'azure']);

    adminPage(ManageKnowledgeSettings::class, $this->admin)
        ->assertSee(['Read by Azure Document Intelligence', '.doc, .rtf by the app · .docx by Azure Document Intelligence']);
});

it('greys out the types nothing can read, and keeps them saved', function (): void {
    saveSettings(AiSettings::class, ['document_parser' => 'none', 'transcriber' => 'none']);

    adminPage(ManageKnowledgeSettings::class, $this->admin)
        ->assertSee(['Needs a document reader (Settings', 'Needs speech to text (Settings', '6 cannot be read yet'])
        ->fillForm(['file_types' => ['pdf', 'text', 'audio']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(KnowledgeSettings::class, 'file_types'))->toBe(['pdf', 'text', 'audio'])
        ->and(KnowledgeFileType::enabled())->toBe([KnowledgeFileType::Text]);
});

it('shows what agents learn from, and leaves search to its own page', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(ManageKnowledgeSettings::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['File types', 'Web scraper', 'AI enhancement'])
        ->assertDontSee(['Hybrid search', 'Follow-up questions', 'Fine-tuning', 'Try splitting']);
});

it('shows on the overview whether websites can be imported', function (): void {
    expect(app(ManageKnowledgeSettings::class)->overviewStatus())->toBe(['Web scraper off', 'gray']);

    saveSettings(KnowledgeSettings::class, ['firecrawl_api_key' => 'fc-key']);

    expect(app(ManageKnowledgeSettings::class)->overviewStatus())->toBe(['Web scraper on', 'success']);
});

it('keeps Firecrawl\'s address and rate in the card\'s Advanced part, and saves them', function (): void {
    adminPage(ManageKnowledgeSettings::class, $this->admin)
        ->fillForm(['firecrawl_rate_per_minute' => 30])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(KnowledgeSettings::class, 'firecrawl_rate_per_minute'))->toBe(30);

    $this->actingAs($this->admin, 'admin')
        ->get(ManageKnowledgeSettings::getUrl())
        ->assertSee('Changed: Pages per minute.');
});
