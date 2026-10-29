<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Actions\Account\PurgeAgentAction;
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

/**
 * The purge of a deleted agent: its leads, chats and sessions go in small
 * batches, then its settings, chat memory and files, then its audit and
 * guardrail rows lose their agent (they are kept), and finally the agent row.
 * Another agent's data stays, a live agent is refused, and a retry after a
 * failure finishes the job.
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

it('removes the agent data and files, keeps its audit rows without the agent, and leaves another agent alone', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);
    $conversation = pgaConversation($agent);
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $lead = FormSubmission::factory()->forAgent($agent)->create();
    $activity = AgentActivity::factory()->forAgent($agent)->create();
    $event = GuardrailEvent::factory()->forAgent($agent)->create();

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

    $kept = pgaConversation($other);
    $keptLead = FormSubmission::factory()->forDefinition(FormDefinition::factory()->forAgent($other)->create())->create();
    $otherActivity = AgentActivity::factory()->forAgent($other)->create();
    $other->setSetting('title', 'Hi', 'appearance');

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
        ->and(Cache::driver('chat')->tags([$tag])->has('memory'))->toBeFalse()
        ->and($activity->fresh()->agent_id)->toBeNull()
        ->and($event->fresh()->agent_id)->toBeNull()
        ->and(Agent::find($other->id))->not->toBeNull()
        ->and(Message::query()->where('chat_conversation_id', $kept->id)->count())->toBe(2)
        ->and(FormSubmission::find($keptLead->id))->not->toBeNull()
        ->and($otherActivity->fresh()->agent_id)->toBe($other->id)
        ->and($other->modelSettings()->where('group', 'appearance')->where('key', 'title')->exists())->toBeTrue()
        ->and(Storage::allFiles(KnowledgeStoragePath::forSource($file)))->toBe([]);
    Storage::assertMissing($prefix.'/notes.txt');
    Storage::disk(Attachment::DISK)->assertMissing($prefix.'/attachments/a.png');
    Storage::disk('assets')->assertMissing($prefix.'/settings/appearance/logo.png');
});

it('deletes and clears in small batches, the leads before their conversations', function (): void {
    $agent = Agent::factory()->create();
    $conversations = Collection::times(5, fn (): Conversation => pgaConversation($agent));
    Session::factory()->count(2)->create(['agent_id' => $agent->id]);
    FormSubmission::factory()->forAgent($agent)->create(['chat_conversation_id' => $conversations->first()->id]);
    AgentActivity::factory()->forAgent($agent)->count(3)->create();

    $statements = [];
    DB::listen(function (QueryExecuted $query) use (&$statements): void {
        if (preg_match('/^(?:DELETE FROM|UPDATE) (form_submissions|chat_conversations|chat_sessions|agent_activities) /', $query->sql, $match) === 1) {
            $statements[] = $match[1];
        }
    });

    $agent->delete();
    new PurgeAgentAction(conversationBatch: 2, rowBatch: 3)->execute($agent);

    $counts = array_count_values($statements);

    expect(array_values(array_intersect(array_unique($statements), ['form_submissions', 'chat_conversations'])))->toBe(['form_submissions', 'chat_conversations'])
        ->and($counts['chat_conversations'])->toBe(4)
        ->and($counts['chat_sessions'])->toBe(4)
        ->and($counts['agent_activities'])->toBe(2)
        ->and(Conversation::withTrashed()->where('agent_id', $agent->id)->exists())->toBeFalse()
        ->and(Session::query()->where('agent_id', $agent->id)->exists())->toBeFalse();
});

it('finishes the purge when run again after a failure', function (): void {
    $agent = Agent::factory()->create();
    $conversation = pgaConversation($agent);
    $outage = new ArrayObject(['on' => true]);
    Agent::forceDeleting(function () use ($outage): void {
        if ($outage['on']) {
            throw new RuntimeException('boom');
        }
    });
    $agent->delete();

    expect(fn () => app(PurgeAgentAction::class)->execute($agent))->toThrow(RuntimeException::class);

    expect(Conversation::withTrashed()->find($conversation->id))->toBeNull()
        ->and(Agent::onlyTrashed()->find($agent->id))->not->toBeNull();

    $outage['on'] = false;
    app(PurgeAgentAction::class)->execute(Agent::onlyTrashed()->findOrFail($agent->id));

    expect(Agent::withTrashed()->find($agent->id))->toBeNull();
});

it('refuses to purge an agent that is not deleted and removes nothing', function (): void {
    $agent = Agent::factory()->create();
    $conversation = pgaConversation($agent);

    expect(fn () => app(PurgeAgentAction::class)->execute($agent))->toThrow(LogicException::class);

    expect(Agent::find($agent->id))->not->toBeNull()
        ->and(Conversation::find($conversation->id))->not->toBeNull();
});
