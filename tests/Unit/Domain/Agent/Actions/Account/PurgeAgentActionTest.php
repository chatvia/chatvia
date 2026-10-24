<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Actions\Account\PurgeAgentAction;
use Domain\Agent\Jobs\PurgeAgentJob;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\ModelSetting;
use Domain\Conversation\Models\AgentActivity;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Guardrail\Models\GuardrailEvent;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The purge of a deleted agent: its leads, chats and sessions go in small
 * batches, then its settings, chat memory and files, then its audit and
 * guardrail rows lose their agent (they are kept), and finally the agent row.
 * Another agent's data stays, and a retry after a failure finishes the job.
 * Which sources go is in PurgeAgentSourcesTest.
 */
beforeEach(function (): void {
    Storage::fake();
    Storage::fake('assets');
});

/** A conversation of the agent with two messages and one attachment row. */
function pgaConversation(Agent $agent): Conversation
{
    $conversation = Conversation::factory()->create(['agent_id' => $agent->id]);
    Message::factory()->count(2)->create(['chat_conversation_id' => $conversation->id]);
    Attachment::factory()->forConversation($conversation)->create();

    return $conversation;
}

/** Count the DELETE statements run against one table while $callback runs. */
function pgaCountDeletes(string $table, Closure $callback): int
{
    $count = 0;

    DB::listen(function (QueryExecuted $query) use ($table, &$count): void {
        if (Str::startsWith($query->sql, "DELETE FROM {$table} ")) {
            $count++;
        }
    });

    $callback();

    return $count;
}

it('removes the agent chats, leads, sources, settings, memory and files, then the agent', function (): void {
    $agent = Agent::factory()->create();
    $conversation = pgaConversation($agent);
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $lead = FormSubmission::factory()->forAgent($agent)->create();

    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->files()->attach($file->id);

    $agent->setSetting('title', 'Hi', 'appearance');

    $tag = ChatCache::agentTag($agent->external_id);
    Cache::driver('chat')->tags([$tag])->forever('memory', ['x']);

    $prefix = 'workspaces/'.$agent->workspaceExternalId().'/agents/'.$agent->external_id;
    Storage::put($prefix.'/notes.txt', 'x');
    Storage::disk(Attachment::DISK)->put($prefix.'/attachments/a.png', 'x');
    Storage::disk('assets')->put($prefix.'/settings/appearance/logo.png', 'x');
    Storage::put($file->filepath, 'x');
    Storage::put(KnowledgeStoragePath::forSource($file, 'images').'/page.jpg', 'x');

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

    Storage::assertMissing($prefix.'/notes.txt');
    Storage::disk(Attachment::DISK)->assertMissing($prefix.'/attachments/a.png');
    Storage::disk('assets')->assertMissing($prefix.'/settings/appearance/logo.png');
    expect(Storage::allFiles(KnowledgeStoragePath::forSource($file)))->toBe([]);
});

it('deletes conversations and sessions in small batches', function (): void {
    $agent = Agent::factory()->create();

    // Five conversations, each with its own session, plus two more sessions.
    Collection::times(5, fn (): Conversation => pgaConversation($agent));
    Session::factory()->count(2)->create(['agent_id' => $agent->id]);

    $agent->delete();
    $purge = new PurgeAgentAction(conversationBatch: 2, rowBatch: 3);

    $conversationDeletes = 0;
    $sessionDeletes = pgaCountDeletes('chat_sessions', function () use ($purge, $agent, &$conversationDeletes): void {
        $conversationDeletes = pgaCountDeletes('chat_conversations', fn () => $purge->execute($agent));
    });

    // 5 rows in batches of 2 → 3 batches, then 1 that finds nothing; 7 in batches of 3 → 3 + 1.
    expect($conversationDeletes)->toBe(4)
        ->and($sessionDeletes)->toBe(4)
        ->and(Conversation::withTrashed()->where('agent_id', $agent->id)->exists())->toBeFalse()
        ->and(Session::query()->where('agent_id', $agent->id)->exists())->toBeFalse();
});

it('deletes the leads before the conversations they came from', function (): void {
    $agent = Agent::factory()->create();
    $conversation = pgaConversation($agent);
    $lead = FormSubmission::factory()->forAgent($agent)->create(['chat_conversation_id' => $conversation->id]);

    $statements = [];
    DB::listen(function (QueryExecuted $query) use (&$statements): void {
        if (preg_match('/^DELETE FROM (form_submissions|chat_conversations) /', $query->sql, $match) === 1) {
            $statements[] = $match[1];
        }
    });

    $agent->delete();
    app(PurgeAgentAction::class)->execute($agent);

    expect(array_values(array_unique($statements)))->toBe(['form_submissions', 'chat_conversations'])
        ->and(FormSubmission::find($lead->id))->toBeNull();
});

it('keeps the agent audit and guardrail rows, cleared of the agent in batches', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);

    $activities = AgentActivity::factory()->forAgent($agent)->count(3)->create();
    $events = GuardrailEvent::factory()->forAgent($agent)->count(3)->create();
    $otherActivity = AgentActivity::factory()->forAgent($other)->create();
    $otherEvent = GuardrailEvent::factory()->forAgent($other)->create();

    $updates = ['agent_activities' => 0, 'guardrail_events' => 0];
    DB::listen(function (QueryExecuted $query) use (&$updates): void {
        if (preg_match('/^UPDATE (agent_activities|guardrail_events) SET agent_id = NULL /', $query->sql, $match) === 1) {
            $updates[$match[1]]++;
        }
    });

    $agent->delete();
    new PurgeAgentAction(rowBatch: 2)->execute($agent);

    // 3 rows in batches of 2 → 2 batches, then 1 that finds nothing.
    expect($updates)->toBe(['agent_activities' => 3, 'guardrail_events' => 3])
        ->and(AgentActivity::query()->whereKey($activities->pluck('id'))->whereNull('agent_id')->count())->toBe(3)
        ->and(GuardrailEvent::query()->whereKey($events->pluck('id'))->whereNull('agent_id')->count())->toBe(3)
        ->and($otherActivity->fresh()->agent_id)->toBe($other->id)
        ->and($otherEvent->fresh()->agent_id)->toBe($other->id)
        ->and(Agent::withTrashed()->find($agent->id))->toBeNull();
});

it('leaves another agent of the workspace alone', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);

    pgaConversation($agent);
    $kept = pgaConversation($other);
    // The form is the other agent's too: deleting a form takes its leads.
    $keptLead = FormSubmission::factory()->forDefinition(FormDefinition::factory()->forAgent($other)->create())->create();
    $other->setSetting('title', 'Hi', 'appearance');

    $agent->delete();
    app(PurgeAgentAction::class)->execute($agent);

    expect(Agent::find($other->id))->not->toBeNull()
        ->and(Conversation::find($kept->id))->not->toBeNull()
        ->and(Message::query()->where('chat_conversation_id', $kept->id)->count())->toBe(2)
        ->and(FormSubmission::find($keptLead->id))->not->toBeNull()
        ->and($other->modelSettings()->where('group', 'appearance')->where('key', 'title')->exists())->toBeTrue();
});

it('finishes the purge when run again after a failure', function (): void {
    $agent = Agent::factory()->create();
    $conversation = pgaConversation($agent);

    // The final delete fails once (an outage), then works on the retry.
    $outage = new ArrayObject(['on' => true]);
    Agent::forceDeleting(function () use ($outage): void {
        if ($outage['on']) {
            throw new RuntimeException('boom');
        }
    });

    $agent->delete();

    expect(fn () => app(PurgeAgentAction::class)->execute($agent))->toThrow(RuntimeException::class);

    // The batches that ran are done; only the agent row is left.
    expect(Conversation::withTrashed()->find($conversation->id))->toBeNull()
        ->and(Agent::onlyTrashed()->find($agent->id))->not->toBeNull();

    $outage['on'] = false;
    app(PurgeAgentAction::class)->execute(Agent::onlyTrashed()->findOrFail($agent->id));

    expect(Agent::withTrashed()->find($agent->id))->toBeNull();
});

it('refuses to purge an agent that is not deleted', function (): void {
    $agent = Agent::factory()->create();
    $conversation = pgaConversation($agent);

    expect(fn () => app(PurgeAgentAction::class)->execute($agent))->toThrow(LogicException::class);

    expect(Conversation::find($conversation->id))->not->toBeNull();
});

it('runs from a job that skips a live or purged agent', function (): void {
    $live = Agent::factory()->create();
    $conversation = pgaConversation($live);

    new PurgeAgentJob($live->id)->handle(app(PurgeAgentAction::class));

    // A live agent is never purged, and an id that is gone is a no-op.
    new PurgeAgentJob(PHP_INT_MAX)->handle(app(PurgeAgentAction::class));

    expect(Agent::find($live->id))->not->toBeNull()
        ->and(Conversation::find($conversation->id))->not->toBeNull();
});
