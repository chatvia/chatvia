<?php

/**
 * DeleteUserAccountAction deletes a big workspace in small batches, so no
 * statement holds a long lock: conversations and sessions, and the audit
 * tables (agent activities, guardrail events, security audit log) before the
 * final transaction. Counted by the DELETE statements each table gets, with
 * batches of two.
 */

use App\Identity\Workspace\Actions\Account\DeleteUserAccountAction;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\AgentActivity;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Guardrail\Models\GuardrailEvent;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Counts the DELETE statements each of the tables gets from here on.
 *
 * @param  list<string>  $tables
 * @return ArrayObject<string, int>
 */
function duabDeletesOf(array $tables): ArrayObject
{
    $deletes = new ArrayObject(array_fill_keys($tables, 0));

    DB::listen(function (QueryExecuted $query) use ($deletes, $tables): void {
        foreach ($tables as $table) {
            if (Str::startsWith(Str::lower($query->sql), ["delete from {$table} ", "delete from \"{$table}\" "])) {
                $deletes[$table]++;
            }
        }
    });

    return $deletes;
}

it('deletes the workspace audit logs in batches before the final transaction', function (): void {
    $agent = Agent::factory()->create();
    $otherAgent = Agent::factory()->create();

    foreach ([$agent, $agent, $agent, $otherAgent] as $of) {
        AgentActivity::factory()->forAgent($of)->create();
        GuardrailEvent::factory()->forAgent($of)->create();
        DB::table('security_audit_logs')->insert(['workspace_id' => $of->workspace_id, 'action' => 'login']);
    }

    $tables = ['agent_activities', 'guardrail_events', 'security_audit_logs'];
    $deletes = duabDeletesOf($tables);

    new DeleteUserAccountAction(conversationBatch: 2, rowBatch: 2)->execute($agent->workspace->owner);

    // Three rows per table in batches of 2: two batches, then one that finds nothing.
    expect($deletes->getArrayCopy())->toBe(array_fill_keys($tables, 3));

    foreach ($tables as $table) {
        expect(DB::table($table)->where('workspace_id', $agent->workspace_id)->exists())->toBeFalse()
            ->and(DB::table($table)->where('workspace_id', $otherAgent->workspace_id)->count())->toBe(1);
    }
});

it('deletes conversations and sessions in small batches by workspace', function (): void {
    $agent = Agent::factory()->create();
    $otherAgent = Agent::factory()->create();

    // Five conversations, each with its own session and two messages.
    $conversations = Conversation::factory()->count(5)->create(['agent_id' => $agent->id]);
    $conversations->each(fn (Conversation $conversation) => Message::factory()->count(2)->create(['chat_conversation_id' => $conversation->id]));
    $kept = Conversation::factory()->create(['agent_id' => $otherAgent->id]);

    $deletes = duabDeletesOf(['chat_conversations', 'chat_sessions']);

    new DeleteUserAccountAction(conversationBatch: 2, rowBatch: 2)->execute($agent->workspace->owner);

    // 5 rows in batches of 2: 3 batches, then 1 that finds nothing.
    expect($deletes->getArrayCopy())->toBe(['chat_conversations' => 4, 'chat_sessions' => 4])
        ->and(Conversation::withTrashed()->whereKey($conversations->modelKeys())->exists())->toBeFalse()
        ->and(Message::query()->whereIn('chat_conversation_id', $conversations->modelKeys())->exists())->toBeFalse()
        ->and(Conversation::find($kept->id))->not->toBeNull();
});
