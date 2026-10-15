<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Actions\Account\DeleteAccountAction;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\WorkspaceActivation;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * WorkspaceActivation answers "may this workspace's agents answer?". A
 * workspace is off while its owner is deactivated (also while its deletion
 * runs). The answer is cached, and Deactivate / Reactivate drop it at once.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->activation = app(WorkspaceActivation::class);
});

it('keeps a workspace on by default', function (): void {
    expect($this->activation->isActive($this->agent->workspace_id))->toBeTrue();
});

it('switches the workspace off when its owner is deactivated, and on again when reactivated', function (): void {
    // Warm the cache first, so the actions must drop it.
    expect($this->activation->isActive($this->agent->workspace_id))->toBeTrue();

    deactivateWorkspaceOf($this->agent);

    expect($this->activation->isActive($this->agent->workspace_id))->toBeFalse();

    reactivateWorkspaceOf($this->agent);

    expect($this->activation->isActive($this->agent->workspace_id))->toBeTrue();
});

it('switches the workspace off while its deletion runs', function (): void {
    Queue::fake();

    app(DeleteAccountAction::class)->execute($this->agent->workspace->owner);

    expect($this->activation->isActive($this->agent->workspace_id))->toBeFalse();
});

it('keeps the workspace on when only a teammate or a chatter is deactivated', function (): void {
    $owner = $this->agent->workspace->owner;
    $deactivate = app(DeactivateAccountAction::class);

    $deactivate->execute(User::factory()->employee($owner)->create(), 'left the company');
    $deactivate->execute(Chatter::factory()->ofWorkspace($owner->workspace())->create(), 'spam');

    expect($this->activation->isActive($this->agent->workspace_id))->toBeTrue();
});

it('does not switch off other workspaces', function (): void {
    $other = Agent::factory()->create();

    deactivateWorkspaceOf($this->agent);

    expect($this->activation->isActive($other->workspace_id))->toBeTrue();
});

it('answers from the cache without a query once warmed', function (): void {
    $this->activation->isActive($this->agent->workspace_id);

    DB::enableQueryLog();
    $this->activation->isActive($this->agent->workspace_id);

    expect(DB::getQueryLog())->toBeEmpty();
});
