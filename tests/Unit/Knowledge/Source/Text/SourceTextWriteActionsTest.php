<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Ingestion\Jobs\Text\TextIngestionJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Text\Actions\CreateAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Actions\UpdateAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Support\Exceptions\DomainValidationException;

/**
 * Creating or replacing a text source writes a file and several rows. The rows
 * are written in one transaction, a failed write removes the new file, and an
 * update deletes the old file only after the row points at the new one. A
 * workspace holds each text once, and new content gets a new summary.
 */
function textWriterOwner(): User
{
    $owner = User::factory()->create();

    app(TeamService::class)->createDefaultTeam($owner);

    return User::query()->findOrFail($owner->id);
}

beforeEach(function (): void {
    Storage::fake();
    Bus::fake();
});

it('replaces the file only after the row points at the new one', function (): void {
    $owner = textWriterOwner();
    $agent = createAgent($owner);

    $text = app(CreateAgentSourceTextAction::class)->execute($agent, 'The original text about our product.');
    $oldPath = $text->filepath;

    $changed = app(UpdateAgentSourceTextAction::class)->execute($agent, $text, 'A new text about our product.');

    $text->refresh();

    expect($changed)->toBeTrue()
        ->and($text->filepath)->not->toBe($oldPath)
        ->and(Storage::get($text->filepath))->toBe('A new text about our product.')
        ->and(Storage::exists($oldPath))->toBeFalse();

    Bus::assertDispatchedTimes(TextIngestionJob::class, 2);
});

it('keeps the old file and row when the update fails', function (): void {
    $owner = textWriterOwner();
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
    $owner = textWriterOwner();
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

function textWriterChunk(SourceText $text, int $index): Chunk
{
    return Chunk::query()->create([
        'workspace_id' => $text->workspace_id,
        'chunkable_type' => $text->getMorphClass(),
        'chunkable_id' => $text->id,
        'chunk_index' => $index,
        'chunk' => "chunk {$index}",
        'tokens' => 2,
        'embedding' => '['.implode(',', array_fill(0, 1024, 0.1)).']',
        'embedding_bm25' => Bm25Tokenizer::expression("chunk {$index}"),
        'checksum' => md5(uniqid()),
        'cost_in_micro_cents' => 0,
    ]);
}

it('deletes the summary chunk with the others when the content changes, so the summary is written again', function (): void {
    $owner = textWriterOwner();
    $agent = createAgent($owner);
    $text = app(CreateAgentSourceTextAction::class)->execute($agent, 'The original text about our product.');
    textWriterChunk($text, 0);
    textWriterChunk($text, 1);

    app(UpdateAgentSourceTextAction::class)->execute($agent, $text, 'A new text about our product.');

    expect(Chunk::query()->ofSource($text)->exists())->toBeFalse();
});

it('deletes the summary chunk when a text is retrained', function (): void {
    $owner = textWriterOwner();
    $agent = createAgent($owner);
    $text = app(CreateAgentSourceTextAction::class)->execute($agent, 'The original text about our product.');
    textWriterChunk($text, 0);
    textWriterChunk($text, 1);

    $text->retrain();

    expect(Chunk::query()->ofSource($text)->exists())->toBeFalse()
        ->and($text->fresh()->status)->toBe(SourceStatus::Pending);
});

it('does nothing when the content did not change', function (): void {
    $owner = textWriterOwner();
    $agent = createAgent($owner);
    $text = app(CreateAgentSourceTextAction::class)->execute($agent, 'The original text about our product.');

    expect(app(UpdateAgentSourceTextAction::class)->execute($agent, $text, 'The original text about our product.'))->toBeFalse();
    Bus::assertDispatchedTimes(TextIngestionJob::class, 1);
});

it('refuses a text another text of the workspace already has', function (): void {
    $owner = textWriterOwner();
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
