<?php

use App\Admin\Clusters\Settings\Pages\ManageKnowledgeSettings;
use App\Admin\Settings\AiProviderSettings;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\KnowledgeSettings;
use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Ingestion\Services\DocumentReaders;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The File types card of the admin panel's Knowledge page: every type is on
 * by default, an admin turns some off, and at least one stays on. Under each
 * type it says what reads it now; a type nothing can read is greyed out,
 * and a saved one stays saved.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();

    // A document reader and speech to text, so every type can be read.
    saveSettings(AiProviderSettings::class, ['mistral_api_key' => 'mistral-test', 'scaleway_api_key' => 'scw-test']);
    saveSettings(AiSettings::class, ['document_parser' => 'mistral', 'transcriber' => 'scaleway']);
});

function savedFileTypes(): ?array
{
    app()->forgetScopedInstances();

    return app(KnowledgeSettings::class)->file_types;
}

function ingestionPage(User $admin): Testable
{
    return Livewire::actingAs($admin, 'admin')->test(ManageKnowledgeSettings::class);
}

it('shows every file type turned on by default', function (): void {
    ingestionPage($this->admin)
        ->assertSee('All file types')
        ->assertSchemaStateSet(['file_types' => array_column(KnowledgeFileType::cases(), 'value')]);
});

it('saves the file types the admin keeps on, in the order of the list', function (): void {
    ingestionPage($this->admin)
        ->fillForm(['file_types' => ['video', 'pdf', 'word']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedFileTypes())->toBe(['pdf', 'word', 'video'])
        ->and(config('chatvia.knowledge.file_types'))->toBe(['pdf', 'word', 'video']);

    ingestionPage($this->admin)->assertSee('PDF, Word, Video');
});

it('keeps the file types unsaved while every type stays on', function (): void {
    ingestionPage($this->admin)
        ->fillForm(['file_types' => array_reverse(array_column(KnowledgeFileType::cases(), 'value'))])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedFileTypes())->toBeNull();
});

it('needs at least one file type', function (): void {
    ingestionPage($this->admin)
        ->fillForm(['file_types' => []])
        ->call('save')
        ->assertHasFormErrors(['file_types' => 'required']);

    expect(savedFileTypes())->toBeNull();
});

it('says what reads each file type with Mistral OCR', function (): void {
    ingestionPage($this->admin)
        ->assertSee([
            'Read by Mistral OCR',
            '.doc, .rtf by the app · .docx by Mistral OCR',
            '.ppt by the app · .pptx by Mistral OCR',
            'Turned into text by Scaleway AI',
        ]);
});

it('says what reads each file type with Azure, which leaves Excel to the app', function (): void {
    saveSettings(AiProviderSettings::class, ['mistral_api_key' => null, 'azure_api_key' => 'az-key', 'azure_endpoint' => 'https://docs.cognitiveservices.azure.com']);
    saveSettings(AiSettings::class, ['document_parser' => 'azure']);

    ingestionPage($this->admin)
        ->assertSee(['Read by Azure Document Intelligence', '.doc, .rtf by the app · .docx by Azure Document Intelligence']);

    expect(DocumentReaders::readsItself('azure', 'xlsx'))->toBeFalse()
        ->and(DocumentReaders::readsItself('mistral', 'csv'))->toBeFalse();
});

it('greys out the types nothing can read, and keeps them saved', function (): void {
    saveSettings(AiSettings::class, ['document_parser' => 'none', 'transcriber' => 'none']);

    ingestionPage($this->admin)
        ->assertSee(['Needs a document reader (Settings', 'Needs speech to text (Settings', '6 cannot be read yet'])
        ->fillForm(['file_types' => ['pdf', 'text', 'audio']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedFileTypes())->toBe(['pdf', 'text', 'audio'])
        ->and(KnowledgeFileType::enabled())->toBe([KnowledgeFileType::Text]);
});
