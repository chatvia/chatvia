<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Actions\Account\PurgeAgentAction;
use Domain\Agent\Jobs\PurgeAgentJob;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\ModelSetting;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The purge of a deleted agent: its chats, sessions and leads go in small
 * batches, then its own sources, settings, chat memory and files, and finally
 * the agent row. Another agent's data stays, and a retry after a failure
 * finishes the job.
 */
beforeEach(function (): void {
    Storage::fake();
    Storage::fake('public');
    config(['cache.stores.chat' => ['driver' => 'array']]);
});

/** A conversation of the agent with two messages and one attachment row. */
function purgeTestConversation(Agent $agent): Conversation
{
    $conversation = Conversation::factory()->create(['agent_id' => $agent->id]);
    Message::factory()->count(2)->create(['chat_conversation_id' => $conversation->id]);
    Attachment::create([
        'chat_conversation_id' => $conversation->id,
        'filepath' => 'workspaces/x/agents/'.$agent->external_id.'/attachments/doc.pdf',
        'filename' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 4,
    ]);

    return $conversation;
}

/** Count the DELETE statements run against one table while $callback runs. */
function countDeletes(string $table, Closure $callback): int
{
    $count = 0;

    DB::listen(function (QueryExecuted $query) use ($table, &$count): void {
        if (str_starts_with($query->sql, "DELETE FROM {$table} ")) {
            $count++;
        }
    });

    $callback();

    return $count;
}

it('removes the agent chats, leads, sources, settings, memory and files, then the agent', function (): void {
    $agent = Agent::factory()->create();
    $conversation = purgeTestConversation($agent);
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $lead = FormSubmission::factory()->forAgent($agent)->create();

    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->files()->attach($file->id);

    ModelSetting::create(['model_type' => $agent->getMorphClass(), 'model_id' => $agent->id, 'group' => 'appearance', 'key' => 'title', 'value' => 'Hi', 'data_type' => 'string']);

    $tag = ChatCache::agentTag($agent->external_id);
    Cache::driver('chat')->tags([$tag])->forever('memory', ['x']);

    $prefix = 'workspaces/'.$agent->workspaceExternalId().'/agents/'.$agent->external_id;
    Storage::put($prefix.'/train/file.txt', 'x');
    Storage::disk('public')->put($prefix.'/attachments/a.png', 'x');

    $agent->delete();
    app(PurgeAgentAction::class)->execute($agent);

    expect(Agent::withTrashed()->find($agent->id))->toBeNull()
        ->and(Conversation::withTrashed()->find($conversation->id))->toBeNull()
        ->and(Message::query()->where('chat_conversation_id', $conversation->id)->exists())->toBeFalse()
        ->and(Attachment::query()->where('chat_conversation_id', $conversation->id)->exists())->toBeFalse()
        ->and(Session::find($session->id))->toBeNull()
        ->and(FormSubmission::find($lead->id))->toBeNull()
        ->and(SourceFile::find($file->id))->toBeNull()
        ->and(ModelSetting::query()->where('model_type', $agent->getMorphClass())->where('model_id', $agent->id)->exists())->toBeFalse()
        ->and(Cache::driver('chat')->tags([$tag])->has('memory'))->toBeFalse();

    Storage::assertMissing($prefix.'/train/file.txt');
    Storage::disk('public')->assertMissing($prefix.'/attachments/a.png');
});

it('deletes conversations and sessions in small batches', function (): void {
    $agent = Agent::factory()->create();

    // Five conversations, each with its own session, plus two more sessions.
    foreach (range(1, 5) as $ignored) {
        purgeTestConversation($agent);
    }
    Session::factory()->count(2)->create(['agent_id' => $agent->id]);

    $agent->delete();
    $purge = new PurgeAgentAction(conversationBatch: 2, rowBatch: 3);

    $conversationDeletes = 0;
    $sessionDeletes = countDeletes('chat_sessions', function () use ($purge, $agent, &$conversationDeletes): void {
        $conversationDeletes = countDeletes('chat_conversations', fn () => $purge->execute($agent));
    });

    // 5 rows in batches of 2 → 3 batches, then 1 that finds nothing; 7 in batches of 3 → 3 + 1.
    expect($conversationDeletes)->toBe(4)
        ->and($sessionDeletes)->toBe(4)
        ->and(Conversation::withTrashed()->where('agent_id', $agent->id)->exists())->toBeFalse()
        ->and(Session::query()->where('agent_id', $agent->id)->exists())->toBeFalse();
});

it('leaves another agent of the workspace alone', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);

    purgeTestConversation($agent);
    $kept = purgeTestConversation($other);
    // The form is the other agent's too: deleting a form takes its leads.
    $keptLead = FormSubmission::factory()->forDefinition(FormDefinition::factory()->forAgent($other)->create())->create();
    $keptSetting = ModelSetting::create(['model_type' => $other->getMorphClass(), 'model_id' => $other->id, 'group' => 'appearance', 'key' => 'title', 'value' => 'Hi', 'data_type' => 'string']);

    $shared = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->files()->attach($shared->id);
    $other->files()->attach($shared->id);

    $agent->delete();
    app(PurgeAgentAction::class)->execute($agent);

    expect(Agent::find($other->id))->not->toBeNull()
        ->and(Conversation::find($kept->id))->not->toBeNull()
        ->and(Message::query()->where('chat_conversation_id', $kept->id)->count())->toBe(2)
        ->and(FormSubmission::find($keptLead->id))->not->toBeNull()
        ->and(ModelSetting::find($keptSetting->id))->not->toBeNull()
        ->and(SourceFile::find($shared->id))->not->toBeNull();
});

it('deletes a source shared only with another deleted agent', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);

    $shared = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->files()->attach($shared->id);
    $other->files()->attach($shared->id);

    // Both agents were deleted; the other one's purge has not run yet.
    $other->delete();
    $agent->delete();
    app(PurgeAgentAction::class)->execute($agent);

    expect(SourceFile::find($shared->id))->toBeNull();
});

it('finishes the purge when run again after a failure', function (): void {
    $agent = Agent::factory()->create();
    $conversation = purgeTestConversation($agent);

    $fail = true;
    Agent::forceDeleting(function () use (&$fail): void {
        if ($fail) {
            throw new RuntimeException('boom');
        }
    });

    $agent->delete();

    expect(fn () => app(PurgeAgentAction::class)->execute($agent))->toThrow(RuntimeException::class);

    // The batches that ran are done; only the agent row is left.
    expect(Conversation::withTrashed()->find($conversation->id))->toBeNull()
        ->and(Agent::onlyTrashed()->find($agent->id))->not->toBeNull();

    $fail = false;
    app(PurgeAgentAction::class)->execute(Agent::onlyTrashed()->findOrFail($agent->id));

    expect(Agent::withTrashed()->find($agent->id))->toBeNull();
});

it('refuses to purge an agent that is not deleted', function (): void {
    $agent = Agent::factory()->create();
    $conversation = purgeTestConversation($agent);

    expect(fn () => app(PurgeAgentAction::class)->execute($agent))->toThrow(LogicException::class);

    expect(Conversation::find($conversation->id))->not->toBeNull();
});

it('runs from a job on the exports queue that skips a live or purged agent', function (): void {
    $live = Agent::factory()->create();
    $conversation = purgeTestConversation($live);

    $job = new PurgeAgentJob($live->id);
    $job->handle(app(PurgeAgentAction::class));

    // A live agent is never purged, and an id that is gone is a no-op.
    (new PurgeAgentJob(PHP_INT_MAX))->handle(app(PurgeAgentAction::class));

    expect($job->queue)->toBe('exports')
        ->and($job->timeout)->toBe(1800)
        ->and(Agent::find($live->id))->not->toBeNull()
        ->and(Conversation::find($conversation->id))->not->toBeNull();
});
