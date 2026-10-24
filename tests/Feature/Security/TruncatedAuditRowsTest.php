<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Actions\Tools\RecordToolCallAuditAction;
use Domain\Conversation\Events\ToolCallCompleted;
use Domain\Membership\Team\Services\TeamService;
use Domain\Security\Actions\RecordSecurityEventAction;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Audit rows whose payload was cut at the size limit
|--------------------------------------------------------------------------
|
| A payload over the cap is stored as preview text with a null jsonb column.
| Every audit reader (the governance and agent audit pages, the API audit
| log and the security audit viewer) must render such a row, not fail on it.
*/

beforeEach(function (): void {
    config(['chatvia.governance.max_arg_bytes' => 40, 'chatvia.security_audit.max_metadata_bytes' => 20]);

    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->agent = createAgent($this->owner);

    app(RecordToolCallAuditAction::class)->execute(new ToolCallCompleted(
        agentId: $this->agent->id,
        agentName: $this->agent->name,
        workspaceId: $this->agent->workspace_id,
        userId: null,
        userName: 'Jane',
        conversationExternalId: (string) Str::uuid(),
        traceId: (string) Str::uuid(),
        requestId: 'req_1',
        toolCallId: 'call_1',
        toolName: 'get_weather',
        toolKind: 'function',
        connectorSlug: null,
        connectorName: null,
        arguments: (string) json_encode(['note' => Str::repeat('x', 200)]),
        resultText: 'Sunny',
        isError: false,
        latencyMs: 12,
        gated: false,
        approvalOutcome: 'auto',
    ));
});

it('renders a cut tool-call row on the governance audit page', function (): void {
    $this->actingAs($this->owner)
        ->get(route('governance.audit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('rows.data.0.arguments', null)
            ->where('rows.data.0.arguments_truncated', true)
            ->where('rows.data.0.arguments_preview', fn (string $text): bool => Str::startsWith($text, '{"note":"xxx') && strlen($text) === 40));
});

it('renders a cut tool-call row on the agent audit page', function (): void {
    $this->actingAs($this->owner)
        ->get(route('agents.audit', $this->agent->external_id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('rows.data.0.arguments', null)
            ->where('rows.data.0.arguments_truncated', true));
});

it('returns a cut tool-call row from both API audit logs', function (): void {
    $token = $this->owner->createToken('account')->plainTextToken;

    foreach (['/api/v1/audit', "/api/v1/agents/{$this->agent->external_id}/audit"] as $url) {
        $this->withToken($token)
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.0.arguments', null)
            ->assertJsonPath('data.0.arguments_truncated', true)
            ->assertJsonPath('data.0.arguments_preview', fn (string $text): bool => strlen($text) === 40);
    }
});

it('renders a cut security row on the security audit viewer', function (): void {
    app(RecordSecurityEventAction::class)->execute(SecurityEventOccurred::for(
        action: SecurityAuditAction::PolicyChanged,
        workspaceId: $this->owner->workspace()->id,
        actor: SecurityActorData::forUser($this->owner),
        metadata: ['blob' => Str::repeat('x', 200)],
    ));

    $this->actingAs($this->owner)
        ->get(route('security.audit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('rows.data.0.metadata', null)
            ->where('rows.data.0.metadata_truncated', true)
            ->where('rows.data.0.metadata_preview', fn (string $text): bool => strlen($text) === 20));
});
