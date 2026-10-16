<?php

declare(strict_types=1);

use Domain\Form\Data\CapturedSubmissionData;
use Domain\Form\Enums\FormType;
use Domain\Form\Events\FormSubmitted;

/**
 * FormSubmitted is handled by a queued listener, so it waits in the queue as a
 * serialized object and must come back whole, submission and all.
 */
it('comes back from the queue with the whole submission', function (): void {
    $event = new FormSubmitted(new CapturedSubmissionData(
        agentId: 3,
        workspaceId: 7,
        submissionId: 42,
        formSlug: 'book-a-demo',
        formType: FormType::Booking,
        conversationExternalId: '0199b2c4-0000-7000-8000-000000000001',
        created: true,
        source: 'form',
        values: ['email' => 'jane@example.com', 'seats' => 5],
    ));

    expect(unserialize(serialize($event)))->toEqual($event);
});
