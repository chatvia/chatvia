<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Ingestion\Jobs\Text\TextIngestionJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Knowledge\Source\Text\Actions\CreateAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Actions\DeleteAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Actions\UpdateAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Support\Exceptions\DomainValidationException;

/**
 * Creating or replacing a text source writes a file and several rows. The rows
 * are written in one transaction, a failed write removes the new file, and an
 * update deletes the old file only after the row points at the new one. A
 * workspace holds each text once, and new content gets a new summary.
 */
beforeEach(function (): void {
    Storage::fake();
    Bus::fake();
});

it('replaces the file only after the row points at the new one', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);

    $text = app(CreateAgentSourceTextAction::class)->execute($agent, 'The original text about our product.');
    $oldPath = $text->filepath;

    $changed = app(UpdateAgentSourceTextAction::class)->execute($agent, $text, 'A new text about our product.');

    $text->refresh();

    expect($changed)->toBeTrue()
        ->and($oldPath)->toStartWith(KnowledgeStoragePath::forSource($text).'/')
        ->and($text->filepath)->not->toBe($oldPath)
        ->and($text->filepath)->toStartWith(KnowledgeStoragePath::forSource($text).'/')
        ->and(Storage::get($text->filepath))->toBe('A new text about our product.')
        ->and(Storage::exists($oldPath))->toBeFalse()
        ->and(Storage::allFiles())->toBe([$text->filepath]);

    Bus::assertDispatchedTimes(TextIngestionJob::class, 2);
});

it('stores the text in its own folder, outside the agent, and deleting the text removes the folder', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);

    $text = app(CreateAgentSourceTextAction::class)->execute($agent, 'The original text about our product.');

    expect($text->filepath)->toStartWith('workspaces/'.$agent->workspaceExternalId().'/sources/source_text/'.$text->external_id.'/');
    Storage::assertExists($text->filepath);

    app(DeleteAgentSourceTextAction::class)->execute($agent, $text);

    expect(Storage::allFiles())->toBe([]);
});

it('keeps the old file and row when the update fails', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);

    $text = app(CreateAgentSourceTextAction::class)->execute($agent, 'The original text about our product.');
    $oldPath = $text->filepath;

    SourceText::updating(function (): void {
        throw new RuntimeException('database unavailable');
    });

    expect(fn () => app(UpdateAgentSourceTextAction::class)->execute($agent, $text, 'A new text about our product.'))
        ->toThrow(RuntimeException::class);

    expect(SourceText::query()->find($text->id)->filepath)->toBe($oldPath)
        ->and(Storage::get($oldPath))->toBe('The original text about our product.')
        ->and(Storage::allFiles())->toBe([$oldPath]);

    Bus::assertDispatchedTimes(TextIngestionJob::class, 1);
});

it('leaves no row and no file when creating the text fails half way', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);

    // Fails after the INSERT, before the pivot attach.
    SourceText::created(function (): void {
        throw new RuntimeException('database unavailable');
    });

    expect(fn () => app(CreateAgentSourceTextAction::class)->execute($agent, 'Some text about our product.'))
        ->toThrow(RuntimeException::class);

    expect(SourceText::query()->where('workspace_id', $agent->workspace_id)->exists())->toBeFalse()
        ->and(Storage::allFiles())->toBe([]);

    Bus::assertNotDispatched(TextIngestionJob::class);
});

it('deletes the summary chunk with the others when the content changes, so the summary is written again', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $text = app(CreateAgentSourceTextAction::class)->execute($agent, 'The original text about our product.');
    Chunk::factory()->forSource($text)->summary()->create();
    Chunk::factory()->forSource($text)->create();

    app(UpdateAgentSourceTextAction::class)->execute($agent, $text, 'A new text about our product.');

    expect(Chunk::query()->ofSource($text)->exists())->toBeFalse();
});

it('deletes the summary chunk when a text is retrained', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $text = app(CreateAgentSourceTextAction::class)->execute($agent, 'The original text about our product.');
    Chunk::factory()->forSource($text)->summary()->create();
    Chunk::factory()->forSource($text)->create();

    $text->retrain();

    expect(Chunk::query()->ofSource($text)->exists())->toBeFalse()
        ->and($text->fresh()->status)->toBe(SourceStatus::Pending);
});

it('does nothing when the content did not change', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $text = app(CreateAgentSourceTextAction::class)->execute($agent, 'The original text about our product.');

    expect(app(UpdateAgentSourceTextAction::class)->execute($agent, $text, 'The original text about our product.'))->toBeFalse();
    Bus::assertDispatchedTimes(TextIngestionJob::class, 1);
});

it('refuses a text another text of the workspace already has', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $otherAgent = createAgent($owner);
    app(CreateAgentSourceTextAction::class)->execute($otherAgent, 'A text the workspace already has.');
    $text = app(CreateAgentSourceTextAction::class)->execute($agent, 'Another text about our product.');

    expect(fn () => app(CreateAgentSourceTextAction::class)->execute($agent, 'A text the workspace already has.'))
        ->toThrow(DomainValidationException::class, 'This text is already in your knowledge base.')
        ->and(fn () => app(UpdateAgentSourceTextAction::class)->execute($agent, $text, 'A text the workspace already has.'))
        ->toThrow(DomainValidationException::class);

    expect(SourceText::query()->where('workspace_id', $agent->workspace_id)->count())->toBe(2)
        ->and(Storage::allFiles())->toHaveCount(2);
});
