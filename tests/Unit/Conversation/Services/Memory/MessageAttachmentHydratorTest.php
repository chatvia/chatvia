<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\MessageAttachmentHydrator;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Membership\Team\Services\TeamService;

/**
 * Unit tests for the orphan-attachment clustering decision. Pure logic, no DB —
 * runs regardless of test-schema state. Covers the rules that were previously
 * copy-pasted (and untested) inside two controllers' __invoke().
 */
beforeEach(function (): void {
    $this->hydrator = new MessageAttachmentHydrator;
});

it('maps a claimed attachment to its own message external id', function (): void {
    $owner = $this->hydrator->resolveOwner(
        chatMessageId: 42,
        attachmentCreatedTimestamp: 1_000,
        externalIdByMessageId: [42 => 'msg-ext-42'],
        lastUserExternalId: 'last-user',
        lastUserTimestamp: 2_000,
    );

    expect($owner)->toBe('msg-ext-42');
});

it('returns null for a claimed attachment whose message is not in the lookup', function (): void {
    expect($this->hydrator->resolveOwner(99, 1_000, [42 => 'x'], 'last-user', 2_000))->toBeNull();
});

it('clusters an orphan uploaded before the last user message onto it', function (): void {
    expect($this->hydrator->resolveOwner(null, 1_500, [], 'last-user', 2_000))->toBe('last-user');
});

it('does NOT cluster an orphan uploaded after the last user message (composer file)', function (): void {
    expect($this->hydrator->resolveOwner(null, 2_500, [], 'last-user', 2_000))->toBeNull();
});

it('returns null for an orphan when there is no user message yet', function (): void {
    expect($this->hydrator->resolveOwner(null, 1_500, [], null, null))->toBeNull();
});

it('clusters an orphan with an unknown upload time defensively onto the last user message', function (): void {
    expect($this->hydrator->resolveOwner(null, null, [], 'last-user', 2_000))->toBe('last-user');
});

it('clusters an orphan when the last user timestamp is unknown', function (): void {
    expect($this->hydrator->resolveOwner(null, 2_500, [], 'last-user', null))->toBe('last-user');
});

it('overwrites a form artifact status with the live submission status on reload', function (): void {
    $user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($user);
    $agent = makeTeamAgent($user, 'Bot');
    $session = Session::create(['workspace_id' => $agent->workspace_id, 'agent_id' => $agent->id, 'user_id' => null]);
    $conversation = Conversation::create([
        'chat_session_id' => $session->id,
        'workspace_id' => $agent->workspace_id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::HostedChat,
        'title' => 't',
    ]);
    $definition = FormDefinition::factory()->forAgent($agent)->create();
    $submission = FormSubmission::factory()->forDefinition($definition)->submitted()->create([
        'chat_conversation_id' => $conversation->id,
    ]);

    // Persisted metadata still says 'pending' (the on-submit flip raced / never landed).
    $messages = [[
        'id' => 'msg-1',
        'role' => 'assistant',
        'metadata' => ['artifacts' => [
            ['kind' => 'form', 'data' => ['form_instance_id' => $submission->external_id, 'status' => 'pending']],
        ]],
    ]];

    $hydrated = (new MessageAttachmentHydrator)->hydrate($messages, $conversation);

    expect($hydrated[0]['metadata']['artifacts'][0]['data']['status'])->toBe('submitted');
});
