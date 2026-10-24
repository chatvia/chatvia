<?php

declare(strict_types=1);

namespace Tests\Support\Api;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;

/**
 * The caller most public API tests run as: a workspace owner with the
 * default team, one agent and an account bearer token. A test file takes it
 * with `uses(ActsAsApiOwner::class)` and calls {@see actAsApiOwner()} in its
 * beforeEach; the parts are then `$this->owner`, `$this->agent` and
 * `$this->token`.
 */
trait ActsAsApiOwner
{
    protected function actAsApiOwner(): void
    {
        $this->owner = User::factory()->create();
        app(TeamService::class)->createDefaultTeam($this->owner);
        $this->agent = createAgent($this->owner);
        $this->token = $this->owner->createToken('account')->plainTextToken;
    }
}
