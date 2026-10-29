<?php

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Data\Sessions\ClientOriginData;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\WidgetAuthSessions;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;

/**
 * The widget's session token (`X-AUTH-TOKEN`): it resolves to the staff
 * account or chatter it was issued for, on its own agent, from the same
 * browser, and stops working when that account is switched off.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(CreateDefaultTeamAction::class)->execute($this->owner);
    $this->agent = createAgent($this->owner);
    $this->service = app(WidgetAuthSessions::class);
    $this->origin = new ClientOriginData('203.0.113.10', 'Mozilla/5.0 (Test) Chrome/123 Safari/537');
});

it('resolves a session to the owner or chatter it was issued for, with its meta, keeping same-id accounts apart', function (): void {
    $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData($this->owner->workspace(), $this->agent, 'Jane'));
    $id = sharedStaffAndChatterId();
    $twinStaff = User::factory()->employee($this->owner)->create(['id' => $id]);
    $twinChatter = Chatter::factory()->ofWorkspace($this->owner)->create(['id' => $id]);
    $resolve = fn (Chatter|User $account): Chatter|User|null => $this->service->resolve($this->service->issue($account, $this->agent, $this->origin)['token'], $this->agent, $this->origin);

    $payload = $this->service->resolveWithMeta(
        $this->service->issue($this->owner, $this->agent, $this->origin, ['is_playground' => true])['token'],
        $this->agent,
        $this->origin,
    );

    expect($payload['user']->id)->toBe($this->owner->id)
        ->and($payload['meta']['is_playground'])->toBeTrue()
        ->and($resolve($chatter)?->id)->toBe($chatter->id)
        ->and($resolve($twinChatter))->toBeInstanceOf(Chatter::class)->id->toBe($id)
        ->and($resolve($twinStaff))->toBeInstanceOf(User::class)->id->toBe($id);
});

it('refuses a token of another workspace\'s chatter, another agent or another browser, and an unknown one', function (): void {
    $foreign = Chatter::factory()->ofWorkspace(User::factory()->create())->create();
    $other = createAgent($this->owner);
    $foreignSession = $this->service->issue($foreign, $this->agent, $this->origin);
    $ownerSession = $this->service->issue($this->owner, $this->agent, $this->origin);

    expect($this->service->resolve($foreignSession['token'], $this->agent, $this->origin))->toBeNull()
        ->and($this->service->resolve($ownerSession['token'], $other, $this->origin))->toBeNull()
        ->and($this->service->resolve($ownerSession['token'], $this->agent, new ClientOriginData('198.51.100.250', 'curl/8.0')))->toBeNull()
        ->and($this->service->resolve('nope', $this->agent, $this->origin))->toBeNull();
});

it('rejects a still-cached token the moment the account is deactivated', function (): void {
    $session = $this->service->issue($this->owner, $this->agent, $this->origin);
    $whileActive = $this->service->resolve($session['token'], $this->agent, $this->origin)?->id;

    app(DeactivateAccountAction::class)->execute($this->owner, 'blocked by admin');

    expect($whileActive)->toBe($this->owner->id)
        ->and($this->service->resolve($session['token'], $this->agent, $this->origin))->toBeNull();
});
