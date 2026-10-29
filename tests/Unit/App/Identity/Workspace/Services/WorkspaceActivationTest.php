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

it('keeps a workspace on by default, from the cache once warmed, and off while its owner is deactivated', function (): void {
    $this->activation->isActive($this->agent->workspace_id);
    DB::enableQueryLog();
    $warm = $this->activation->isActive($this->agent->workspace_id);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    deactivateWorkspaceOf($this->agent);
    $whileOff = $this->activation->isActive($this->agent->workspace_id);
    reactivateWorkspaceOf($this->agent);

    expect($warm)->toBeTrue()
        ->and($queries)->toBeEmpty()
        ->and($whileOff)->toBeFalse()
        ->and($this->activation->isActive($this->agent->workspace_id))->toBeTrue();
});

it('switches the workspace off while its deletion runs', function (): void {
    Queue::fake();

    app(DeleteAccountAction::class)->execute($this->agent->workspace->owner);

    expect($this->activation->isActive($this->agent->workspace_id))->toBeFalse();
});

it('keeps the workspace on when only a teammate or chatter is switched off, and other workspaces on', function (): void {
    $owner = $this->agent->workspace->owner;
    $other = Agent::factory()->create();
    $deactivate = app(DeactivateAccountAction::class);

    $deactivate->execute(User::factory()->employee($owner)->create(), 'left the company');
    $deactivate->execute(Chatter::factory()->ofWorkspace($owner->workspace())->create(), 'spam');
    $teammatesOnly = $this->activation->isActive($this->agent->workspace_id);
    deactivateWorkspaceOf($this->agent);

    expect($teammatesOnly)->toBeTrue()
        ->and($this->activation->isActive($other->workspace_id))->toBeTrue();
});
