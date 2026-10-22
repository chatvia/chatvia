<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Actions\ListUserKnowledgeFilesAction;
use Domain\Knowledge\Source\Text\Actions\DeleteAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Actions\Source\DeleteSourceWebsiteAction;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * Deleting a text or website source removes its chunks (summary included) and
 * its agent links; the account-wide file list shows every workspace file with
 * the agent that uses it.
 */
it('deletes a text with its chunks, summary included', function (): void {
    $agent = Agent::factory()->create();
    $text = SourceText::query()->create([
        'workspace_id' => $agent->workspace_id,
        'filepath' => 'users/x/agents/y/train/text/a.txt',
        'excerpt' => 'excerpt',
        'length' => 1,
        'checksum' => md5(uniqid()),
        'status' => SourceStatus::Completed,
    ]);
    $agent->texts()->attach($text->id);
    Chunk::factory()->forSource($text)->summary()->create();
    Chunk::factory()->forSource($text)->create();

    app(DeleteAgentSourceTextAction::class)->execute($agent, $text);

    expect(SourceText::query()->find($text->id))->toBeNull()
        ->and(Chunk::query()->where('chunkable_type', 'source_text')->where('chunkable_id', $text->id)->exists())->toBeFalse()
        ->and($agent->texts()->count())->toBe(0);
});

it('deletes a website with its chunks and agent links', function (): void {
    $agent = Agent::factory()->create();
    $website = SourceWebsite::query()->create(['workspace_id' => $agent->workspace_id, 'url' => 'https://docs.example.test', 'status' => 'ingested']);
    $agent->websites()->attach($website->id);
    Chunk::factory()->forSource($website)->create();

    app(DeleteSourceWebsiteAction::class)->execute($website);

    expect(SourceWebsite::query()->find($website->id))->toBeNull()
        ->and(Chunk::query()->where('chunkable_type', 'source_website')->where('chunkable_id', $website->id)->exists())->toBeFalse()
        ->and($agent->websites()->count())->toBe(0);
});

it('deletes a website chunks before the transaction that deletes the row', function (): void {
    $agent = Agent::factory()->create();
    $website = SourceWebsite::query()->create(['workspace_id' => $agent->workspace_id, 'url' => 'https://docs.example.test', 'status' => 'ingested']);
    $agent->websites()->attach($website->id);
    Chunk::factory()->forSource($website)->count(2)->create();

    $outerLevel = DB::transactionLevel();
    $chunkDeleteLevels = [];
    DB::listen(function (QueryExecuted $query) use (&$chunkDeleteLevels): void {
        if (str_starts_with($query->sql, 'DELETE FROM chunks ')) {
            $chunkDeleteLevels[] = $query->connection->transactionLevel();
        }
    });

    app(DeleteSourceWebsiteAction::class)->execute($website);

    // The batches run outside the action's transaction; the `deleting` hook
    // inside it finds nothing left.
    expect($chunkDeleteLevels[0])->toBe($outerLevel)
        ->and(SourceWebsite::query()->find($website->id))->toBeNull()
        ->and(Chunk::query()->ofSource($website)->exists())->toBeFalse();
});

it('lists the workspace files with the agent that uses each', function (): void {
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'status' => 'ingested']);
    $agent->files()->attach($file->id);
    AgentFileFactory::new()->create(); // another workspace's file

    $rows = app(ListUserKnowledgeFilesAction::class)->execute($agent->workspace->owner);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->id)->toBe($file->id)
        ->and($rows[0]->agent->id)->toBe($agent->id)
        ->and($rows[0]->status)->toBe(SourceStatus::Completed);
});
