<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Ingestion\Jobs\Text\TextIngestionJob;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Knowledge\Source\Text\Actions\CreateAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Actions\UpdateAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Support\Exceptions\DomainValidationException;

/**
 * Writing a text source (CreateAgentSourceTextAction and
 * UpdateAgentSourceTextAction, one behaviour): an update deletes the old file
 * only after the row points at the new one, and a workspace holds each text
 * once.
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
