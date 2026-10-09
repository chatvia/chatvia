<?php

use Domain\Agent\Models\Agent;
use Domain\Form\Data\CapturedSubmissionData;
use Domain\Form\Enums\FormType;
use Domain\Form\Events\FormSubmitted;
use Domain\Form\Listeners\PushSubmissionToCrm;
use Illuminate\Support\Facades\Http;

/**
 * FormSubmitted is handled by a queued listener, so it waits in Redis as a
 * serialized object. Before it carried a DTO the event had nine flat public
 * fields; payloads in that shape can still be queued when a new release goes
 * live, and a worker still on the old release can pick up a payload the new
 * release wrote. Both must keep working: the serialized form stays the flat
 * field list.
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

it('delivers a flat-shape payload to the CRM webhook', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting('crm', [
        'webhook_enabled' => true,
        'webhook_url' => 'https://crm.example.com/hooks/leads',
        'webhook_secret' => 'shh',
    ], 'leads');
    Http::fake(['*' => Http::response('', 200)]);

    $event = unserialize(flatFormSubmittedPayload(flatFormSubmittedFields($agent->id)));
    app(PushSubmissionToCrm::class)->handle($event);

    Http::assertSent(function ($request) use ($agent): bool {
        $payload = $request->data();

        return $request->url() === 'https://crm.example.com/hooks/leads'
            && $payload['form'] === 'book-a-demo'
            && $payload['form_type'] === 'booking'
            && $payload['source'] === 'form'
            && $payload['agent_id'] === $agent->external_id
            && $payload['conversation_id'] === '0199b2c4-0000-7000-8000-000000000001'
            && $payload['values'] === ['email' => 'jane@example.com', 'seats' => 5];
    });
});
