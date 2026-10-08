<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Actions\CreateApiFunctionAction;
use Domain\Integration\Function\Actions\UpdateApiFunctionAction;
use Domain\Integration\Function\Data\ApiFunctionPayloadData;
use Domain\Integration\Function\Models\AgentFunction;
use Illuminate\Validation\ValidationException;

/**
 * The API create/update Actions follow the same rules as the dashboard:
 * names unique per workspace, approval derived from the verb, rows keyed to
 * the workspace owner.
 */
function apiFunctionPayload(array $fields): ApiFunctionPayloadData
{
    return ApiFunctionPayloadData::from($fields);
}

it('keys a function created for a teammate to the workspace owner, with approval from the verb', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->employee($owner)->create();

    $function = app(CreateApiFunctionAction::class)->execute($member, apiFunctionPayload([
        'internal_name' => 'lookup_order',
        'description' => 'Look up an order',
        'parameters_schema' => ['type' => 'object', 'properties' => []],
        'webhook_url' => 'https://example.com/webhook',
        'webhook_method' => 'GET',
    ]));

    expect($function->user_id)->toBe($owner->id)
        ->and($function->requires_approval)->toBeFalse();
});

it('rejects a duplicate name on create and on rename', function (): void {
    $owner = User::factory()->create();
    $base = [
        'description' => 'd',
        'parameters_schema' => ['type' => 'object', 'properties' => []],
        'webhook_url' => 'https://example.com/webhook',
    ];

    app(CreateApiFunctionAction::class)->execute($owner, apiFunctionPayload(['internal_name' => 'taken'] + $base));
    $other = app(CreateApiFunctionAction::class)->execute($owner, apiFunctionPayload(['internal_name' => 'other'] + $base));

    expect(fn () => app(CreateApiFunctionAction::class)->execute($owner, apiFunctionPayload(['internal_name' => 'taken'] + $base)))
        ->toThrow(ValidationException::class);

    expect(fn () => app(UpdateApiFunctionAction::class)->execute($other, apiFunctionPayload(['internal_name' => 'taken'])))
        ->toThrow(ValidationException::class);

    // Renaming to its own current name is not a clash.
    expect(app(UpdateApiFunctionAction::class)->execute($other, apiFunctionPayload(['internal_name' => 'other']))->internal_name)
        ->toBe('other');
});

it('only tightens approval when the verb changes', function (): void {
    $owner = User::factory()->create();
    $function = AgentFunction::query()->create([
        'user_id' => $owner->id,
        'internal_name' => 'fn',
        'description' => 'd',
        'parameters_schema' => ['type' => 'object', 'properties' => []],
        'webhook_url' => 'https://example.com/webhook',
        'webhook_method' => 'POST',
        'requires_approval' => true,
    ]);

    // POST → GET keeps the gate the user had.
    expect(app(UpdateApiFunctionAction::class)->execute($function, apiFunctionPayload(['webhook_method' => 'GET']))->requires_approval)
        ->toBeTrue();

    $function->forceFill(['requires_approval' => false])->save();

    // GET → PATCH turns it on.
    expect(app(UpdateApiFunctionAction::class)->execute($function, apiFunctionPayload(['webhook_method' => 'PATCH']))->requires_approval)
        ->toBeTrue();
});
