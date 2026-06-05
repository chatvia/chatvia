<?php

use Domain\Conversation\Services\Memory\MessageAttachmentHydrator;

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
