<?php

use App\Admin\Clusters\Settings\Pages\ManageKnowledgeSettings;
use App\Admin\Settings\KnowledgeSettings;
use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The File types card of the admin panel's Ingestion page: every type is on
 * by default, an admin turns some off, and at least one stays on.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
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
