<?php

use App\Auth\Models\User;
use Domain\Agents\Models\Agent;
use Domain\Chat\Services\Tooling\Registry\ToolCallerIdentity;

it('maps the agent and owner external ids to identity headers', function (): void {
    $user = new User;
    $user->external_id = 'user-uuid';

    $agent = new Agent;
    $agent->external_id = 'agent-uuid';
    $agent->setRelation('user', $user);

    expect(ToolCallerIdentity::headers($agent))->toBe([
        'X-Chatvia-Agent-Id' => 'agent-uuid',
        'X-Chatvia-User-Id' => 'user-uuid',
    ]);
});
