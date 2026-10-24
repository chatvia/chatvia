<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Account\CreateApiAgentAction;
use Domain\Agent\Data\Account\CreateApiAgentData;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Membership\Team\Services\TeamService;
use Support\Enums\CreatedVia;
use Support\Exceptions\DomainValidationException;

/**
 * CreateApiAgentAction is the public API's (`POST /api/v1/agents`) agent
 * creation. It resolves the target team and (optional) reusable prompt by
 * their external ids, makes the agent stamped `source = api`, and seeds the
 * topics the workspace can use, all in one transaction. The team and prompt
 * resolvers fail with a 422-mapped DomainValidationException, so an unknown
 * id must never create a half-built agent.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->team = app(TeamService::class)->createDefaultTeam($this->owner);
});

it('creates an api-sourced agent owned by the resolved team and attaches the workspace topics', function (): void {
    // System topics belong to no workspace.
    $topicA = Topic::factory()->create(['workspace_id' => null, 'topic' => 'Billing']);
    $topicB = Topic::factory()->create(['workspace_id' => null, 'topic' => 'Support']);

    $agent = app(CreateApiAgentAction::class)->execute($this->owner->workspace(), new CreateApiAgentData(
        name: 'API Bot',
        team_id: $this->team->external_id,
        is_public: true,
        system_instructions: 'Be concise.',
        metadata: ['campaign' => 'launch'],
    ));

    expect($agent->exists)->toBeTrue()
        ->and($agent->name)->toBe('API Bot')
        ->and($agent->workspace_id)->toBe($this->owner->workspace()->id)
        ->and($agent->team_id)->toBe($this->team->id)
        // The public-API surface stamps provenance so usage can be attributed.
        // `source` is cast to the CreatedVia enum on read.
        ->and($agent->source)->toBe(CreatedVia::Api)
        ->and($agent->is_public)->toBeTrue()
        ->and($agent->metadata)->toBe(['campaign' => 'launch'])
        ->and($agent->external_id)->not->toBeEmpty()
        // The generated external id doubles as the initial slug.
        ->and($agent->slug)->toBe($agent->external_id);

    $attachedIds = $agent->topics()->pluck('topics.id');

    expect($attachedIds)->toContain($topicA->id)
        ->and($attachedIds)->toContain($topicB->id)
        ->and($attachedIds->count())->toBe(Topic::forWorkspace($this->owner->workspace())->count());
});

it('stores custom system instructions when no library prompt is supplied', function (): void {
    $agent = app(CreateApiAgentAction::class)->execute($this->owner->workspace(), new CreateApiAgentData(
        name: 'Custom-prompt Bot',
        team_id: $this->team->external_id,
        is_public: false,
        system_instructions: 'You are a helpful assistant.',
    ));

    expect($agent->system_instructions)->toBe('You are a helpful assistant.')
        ->and($agent->agent_prompt_id)->toBeNull();
});

it('links a library prompt and drops custom instructions (mutually exclusive)', function (): void {
    $prompt = AgentPrompt::factory()->create(['workspace_id' => $this->owner->workspace()->id]);

    $agent = app(CreateApiAgentAction::class)->execute($this->owner->workspace(), new CreateApiAgentData(
        name: 'Library-prompt Bot',
        team_id: $this->team->external_id,
        is_public: false,
        prompt_id: $prompt->external_id,
        // Supplied, but must be ignored in favour of the library prompt.
        system_instructions: 'This should be discarded.',
    ));

    expect($agent->agent_prompt_id)->toBe($prompt->id)
        ->and($agent->system_instructions)->toBeNull();
});

it('rejects an unknown team id without creating an agent', function (): void {
    // external_id is a uuid column, so the id must be a valid UUID that simply
    // doesn't exist (a malformed string would fail at the DB type cast, not the
    // resolver's not-found check).
    expect(fn () => app(CreateApiAgentAction::class)->execute($this->owner->workspace(), new CreateApiAgentData(
        name: 'Orphan',
        team_id: '00000000-0000-0000-0000-000000000000',
        is_public: false,
    )))->toThrow(DomainValidationException::class);

    // The owner's team gains no agent — resolution fails before the insert.
    expect($this->team->agents()->count())->toBe(0);
});

it('rejects a prompt id the owner does not own', function (): void {
    // A prompt belonging to a different workspace must not resolve.
    $strangerPrompt = AgentPrompt::factory()->create();

    expect(fn () => app(CreateApiAgentAction::class)->execute($this->owner->workspace(), new CreateApiAgentData(
        name: 'Borrowed prompt',
        team_id: $this->team->external_id,
        is_public: false,
        prompt_id: $strangerPrompt->external_id,
    )))->toThrow(DomainValidationException::class);

    expect($this->team->agents()->count())->toBe(0);
});

it('never attaches another workspace custom topics', function (): void {
    $foreign = Topic::factory()->create();

    $agent = app(CreateApiAgentAction::class)->execute($this->owner->workspace(), new CreateApiAgentData(
        name: 'API Bot',
        team_id: $this->team->external_id,
        is_public: false,
    ));

    expect($agent->topics()->pluck('topics.id'))->not->toContain($foreign->id);
});
