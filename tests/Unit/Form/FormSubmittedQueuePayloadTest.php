<?php

declare(strict_types=1);

use Domain\Form\Data\CapturedSubmissionData;
use Domain\Form\Enums\FormType;
use Domain\Form\Events\FormSubmitted;

/**
 * FormSubmitted is handled by a queued listener, so it waits in the queue as a
 * serialized object. It serializes to a flat list of fields (agentId,
 * workspaceId, … values) rather than the nested DTO, so a payload written by
 * one release can be read by a worker on another: both run side by side while
 * a deploy rolls out, and a waiting CRM delivery must not be lost.
 */

/**
 * A serialized FormSubmitted in the flat shape (field name → value), built
 * the way PHP writes an object's public properties.
 *
 * @param  array<string, mixed>  $fields
 */
function flatFormSubmittedPayload(array $fields): string
{
    $class = FormSubmitted::class;
    $body = implode('', array_map(
        static fn (string $name, mixed $value): string => serialize($name).serialize($value),
        array_keys($fields),
        $fields,
    ));

    return sprintf('O:%d:"%s":%d:{%s}', strlen($class), $class, count($fields), $body);
}

/**
 * @return array<string, mixed>
 */
function flatFormSubmittedFields(int $agentId): array
{
    return [
        'agentId' => $agentId,
        'workspaceId' => 7,
        'submissionId' => 42,
        'formSlug' => 'book-a-demo',
        'formType' => 'booking',
        'conversationExternalId' => '0199b2c4-0000-7000-8000-000000000001',
        'created' => true,
        'source' => 'form',
        'values' => ['email' => 'jane@example.com', 'seats' => 5],
    ];
}

it('reads a payload queued in the flat shape', function (): void {
    $event = unserialize(flatFormSubmittedPayload(flatFormSubmittedFields(3)));

    expect($event)->toBeInstanceOf(FormSubmitted::class)
        ->and($event->submission)->toEqual(new CapturedSubmissionData(
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
});

it('writes the same flat shape, so a worker on the previous release can read it', function (): void {
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

    expect(serialize($event))->toBe(flatFormSubmittedPayload(flatFormSubmittedFields(3)));
});
