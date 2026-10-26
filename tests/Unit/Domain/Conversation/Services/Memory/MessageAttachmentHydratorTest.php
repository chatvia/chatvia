<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
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

it('finds which message an uploaded file belongs to on reload', function (?int $chatMessageId, ?int $uploadedAt, ?string $lastUser, ?int $lastUserAt, ?string $owner): void {
    expect($this->hydrator->resolveOwner(
        chatMessageId: $chatMessageId,
        attachmentCreatedTimestamp: $uploadedAt,
        externalIdByMessageId: [42 => 'msg-ext-42'],
        lastUserExternalId: $lastUser,
        lastUserTimestamp: $lastUserAt,
    ))->toBe($owner);
})->with([
    'sent with a message: that message' => [42, 1_000, 'last-user', 2_000, 'msg-ext-42'],
    'sent with a message not in the lookup: none' => [99, 1_000, 'last-user', 2_000, null],
    'pending, uploaded before the last visitor message: that message' => [null, 1_500, 'last-user', 2_000, 'last-user'],
    'pending, uploaded after the last visitor message: none (still in the composer)' => [null, 2_500, 'last-user', 2_000, null],
    'pending, before any visitor message: none' => [null, 1_500, null, null, null],
    'pending, upload time unknown: the last visitor message' => [null, null, 'last-user', 2_000, 'last-user'],
    'pending, last visitor message time unknown: that message' => [null, 2_500, 'last-user', null, 'last-user'],
]);

it('overwrites a form artifact status with the live submission status on reload', function (): void {
    $agent = makeTeamAgent(User::factory()->create(), 'Bot');
    $conversation = Conversation::factory()->forAgent($agent)->hostedChat()->create();
    $definition = FormDefinition::factory()->forAgent($agent)->create();
    $submission = FormSubmission::factory()->forDefinition($definition)->submitted()->create([
        'chat_conversation_id' => $conversation->id,
    ]);

    // The stored metadata still says 'pending': the change made on submit did not reach it.
    $messages = [new MessageData(
        id: 'msg-1',
        role: MessageRole::Assistant,
        content: 'Here is the form.',
        type: MessageData::TYPE_TEXT,
        timestamp: now()->timestamp,
        metadata: ['artifacts' => [
            ['kind' => 'form', 'data' => ['form_instance_id' => $submission->external_id, 'status' => 'pending']],
        ]],
    )];

    $hydrated = (new MessageAttachmentHydrator)->hydrate($messages, $conversation);

    expect($hydrated[0]->metadata['artifacts'][0]['data']['status'])->toBe('submitted');
});

it('marks a booked calendar artifact booked on reload, with its start time only', function (): void {
    $agent = makeTeamAgent(User::factory()->create(), 'Bot');
    $conversation = Conversation::factory()->forAgent($agent)->hostedChat()->create();
    $definition = FormDefinition::factory()->forAgent($agent)->create();
    $booking = FormSubmission::factory()->forDefinition($definition)->submitted()->create([
        'chat_conversation_id' => $conversation->id,
        'values' => ['email' => 'ada@example.test', 'start_time' => '2026-11-03T10:00:00Z'],
    ]);

    $messages = [new MessageData(
        id: 'msg-1',
        role: MessageRole::Assistant,
        content: 'Pick a time.',
        type: MessageData::TYPE_TEXT,
        timestamp: now()->timestamp,
        metadata: ['artifacts' => [
            ['kind' => 'calendar', 'data' => ['booking_instance_id' => $booking->external_id, 'status' => 'pending', 'booked_start_time' => null]],
        ]],
    )];

    $data = (new MessageAttachmentHydrator)->hydrate($messages, $conversation)[0]->metadata['artifacts'][0]['data'];

    expect($data)->toBe(['booking_instance_id' => $booking->external_id, 'status' => 'submitted', 'booked_start_time' => '2026-11-03T10:00:00Z']);
});
