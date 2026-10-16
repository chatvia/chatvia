<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Managing an agent's topics: renaming and removing the topics it uses, and
 * promoting or dismissing the topic suggestions found in its conversations.
 * Every one of these routes needs agent.topics.manage. Attaching a topic is
 * in AgentTopicAuthorizationTest.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();
    $this->agent = createAgent($this->owner);
});

/** A topic of the agent's workspace, attached to the agent. */
function topicManagementTopic(Agent $agent, string $name): Topic
{
    $topic = Topic::create(['workspace_id' => $agent->workspace_id, 'topic' => $name, 'description' => "About {$name}"]);
    $agent->topics()->attach($topic->id);

    return $topic;
}

/**
 * The topic rows of the agents' conversations.
 *
 * @return Builder<ConversationTopic>
 */
function topicManagementRows(Agent ...$agents): Builder
{
    return ConversationTopic::query()->whereIn('chat_conversation_id', Conversation::query()->select('id')->whereIn('agent_id', array_map(fn (Agent $agent): int => $agent->id, $agents)));
}

/** A suggestion row with this label on a new conversation of the agent. */
function topicManagementSuggestion(Agent $agent, string $label): ConversationTopic
{
    return ConversationTopic::create([
        'workspace_id' => $agent->workspace_id,
        'chat_conversation_id' => Conversation::factory()->create(['agent_id' => $agent->id, 'source' => ConversationSource::Widget])->id,
        'suggested_label' => $label,
    ]);
}

it('renames a topic the agent uses', function (): void {
    $topic = topicManagementTopic($this->agent, 'Refunds');

    $this->actingAs($this->owner)
        ->patchJson(route('agents.topics.update', [$this->agent, $topic]), ['topic' => 'Money back', 'description' => 'Refund requests'])
        ->assertOk()
        ->assertJsonPath('topic.topic', 'Money back');

    expect($topic->fresh())
        ->topic->toBe('Money back')
        ->description->toBe('Refund requests');
});

it('refuses to rename a topic the agent does not use, a system topic, or to a name the workspace has', function (): void {
    $unused = Topic::create(['workspace_id' => $this->agent->workspace_id, 'topic' => 'Unused', 'description' => 'x']);
    $system = Topic::create(['workspace_id' => null, 'topic' => 'Billing '.uniqid(), 'description' => 'x']);
    $this->agent->topics()->attach($system->id);
    $refunds = topicManagementTopic($this->agent, 'Refunds');
    topicManagementTopic($this->agent, 'Shipping');

    $this->actingAs($this->owner)
        ->patchJson(route('agents.topics.update', [$this->agent, $unused]), ['topic' => 'Renamed', 'description' => 'x'])
        ->assertNotFound();
    $this->actingAs($this->owner)
        ->patchJson(route('agents.topics.update', [$this->agent, $system]), ['topic' => 'Renamed', 'description' => 'x'])
        ->assertForbidden();
    $this->actingAs($this->owner)
        ->patchJson(route('agents.topics.update', [$this->agent, $refunds]), ['topic' => 'Shipping', 'description' => 'x'])
        ->assertJsonValidationErrors(['topic']);

    expect($unused->fresh()->topic)->toBe('Unused')
        ->and($system->fresh()->topic)->not->toBe('Renamed')
        ->and($refunds->fresh()->topic)->toBe('Refunds');
});

it('removes a topic from the agent, and deletes a workspace topic no other agent uses', function (): void {
    $only = topicManagementTopic($this->agent, 'Only here');
    $shared = topicManagementTopic($this->agent, 'Shared');
    createAgent($this->owner)->topics()->attach($shared->id);

    $this->actingAs($this->owner)->deleteJson(route('agents.topics.destroy', [$this->agent, $only]))->assertOk();
    $this->actingAs($this->owner)->deleteJson(route('agents.topics.destroy', [$this->agent, $shared]))->assertOk();

    expect($this->agent->topics()->count())->toBe(0)
        ->and(Topic::query()->whereKey($only->id)->exists())->toBeFalse()
        ->and(Topic::query()->whereKey($shared->id)->exists())->toBeTrue();
});

it('promotes a suggestion to a topic of the agent, committing every conversation that carries it', function (): void {
    $first = topicManagementSuggestion($this->agent, 'refunds');
    $second = topicManagementSuggestion($this->agent, 'refunds');

    $this->actingAs($this->owner)
        ->postJson(route('agents.topics.suggestions.promote', $this->agent), ['label' => 'refunds'])
        ->assertOk()
        ->assertJsonPath('topic.topic', 'refunds');

    $topic = $this->agent->topics()->where('topics.topic', 'refunds')->sole();

    expect(topicManagementRows($this->agent)->where('suggested_label', 'refunds')->exists())->toBeFalse()
        ->and(topicManagementRows($this->agent)->where('topic_id', $topic->id)->pluck('chat_conversation_id')->sort()->values()->all())
        ->toBe(collect([$first->chat_conversation_id, $second->chat_conversation_id])->sort()->values()->all());
});

it('dismisses a suggestion for this agent only', function (): void {
    topicManagementSuggestion($this->agent, 'refunds');
    $other = createAgent($this->owner);
    $elsewhere = topicManagementSuggestion($other, 'refunds');

    $this->actingAs($this->owner)
        ->deleteJson(route('agents.topics.suggestions.dismiss', $this->agent), ['label' => 'refunds'])
        ->assertOk();

    expect(topicManagementRows($this->agent, $other)->where('suggested_label', 'refunds')->pluck('id')->all())->toBe([$elsewhere->id])
        ->and($this->agent->topics()->exists())->toBeFalse();
});

describe('agent.topics.manage', function (): void {
    it('keeps a teammate without it, who may read the topic analytics, from managing topics', function (): void {
        $topic = topicManagementTopic($this->agent, 'Refunds');
        topicManagementSuggestion($this->agent, 'shipping');
        $teammate = teammateWithPermissions($this->owner, ['agent.analytics.topics']);

        $this->actingAs($teammate)->getJson(route('agents.topics.suggestions', $this->agent))->assertOk();

        $this->actingAs($teammate)->getJson(route('agents.topics.index', $this->agent))->assertForbidden();
        $this->actingAs($teammate)->postJson(route('agents.topics.store', $this->agent), ['topic' => 'New', 'description' => 'x'])->assertForbidden();
        $this->actingAs($teammate)->patchJson(route('agents.topics.update', [$this->agent, $topic]), ['topic' => 'Renamed', 'description' => 'x'])->assertForbidden();
        $this->actingAs($teammate)->deleteJson(route('agents.topics.destroy', [$this->agent, $topic]))->assertForbidden();
        $this->actingAs($teammate)->postJson(route('agents.topics.suggestions.promote', $this->agent), ['label' => 'shipping'])->assertForbidden();
        $this->actingAs($teammate)->deleteJson(route('agents.topics.suggestions.dismiss', $this->agent), ['label' => 'shipping'])->assertForbidden();

        expect($this->agent->topics()->pluck('topics.topic')->all())->toBe(['Refunds'])
            ->and(topicManagementRows($this->agent)->where('suggested_label', 'shipping')->exists())->toBeTrue();
    });

    it('lets a teammate with it rename, remove, promote and dismiss', function (): void {
        $topic = topicManagementTopic($this->agent, 'Refunds');
        topicManagementSuggestion($this->agent, 'shipping');
        topicManagementSuggestion($this->agent, 'spam');
        $teammate = teammateWithPermissions($this->owner, ['agent.topics.manage']);

        $this->actingAs($teammate)->getJson(route('agents.topics.index', $this->agent))->assertOk()->assertJsonPath('0.topic', 'Refunds');
        $this->actingAs($teammate)->patchJson(route('agents.topics.update', [$this->agent, $topic]), ['topic' => 'Money back', 'description' => 'x'])->assertOk();
        $this->actingAs($teammate)->postJson(route('agents.topics.suggestions.promote', $this->agent), ['label' => 'shipping'])->assertOk();
        $this->actingAs($teammate)->deleteJson(route('agents.topics.suggestions.dismiss', $this->agent), ['label' => 'spam'])->assertOk();
        $this->actingAs($teammate)->deleteJson(route('agents.topics.destroy', [$this->agent, $topic]))->assertOk();

        expect($this->agent->topics()->pluck('topics.topic')->all())->toBe(['shipping'])
            ->and(topicManagementRows($this->agent)->whereNotNull('suggested_label')->exists())->toBeFalse();
    });
});
