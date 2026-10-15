<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\MessageAttachmentHydrator;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;

/**
 * Which message an uploaded file belongs to when a chat is reloaded (pure
 * logic, no DB), and the live status of a form shown in a reply (reads the
 * submission from the DB).
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
    $agent = makeTeamAgent(User::factory()->create(), 'Bot');
    $conversation = Conversation::factory()->withTitle('t')->create([
        'chat_session_id' => Session::factory()->create(['agent_id' => $agent->id])->id,
        'source' => ConversationSource::HostedChat,
    ]);
    $definition = FormDefinition::factory()->forAgent($agent)->create();
    $submission = FormSubmission::factory()->forDefinition($definition)->submitted()->create([
        'chat_conversation_id' => $conversation->id,
    ]);

    // The stored metadata still says 'pending': the change made on submit did not reach it.
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
