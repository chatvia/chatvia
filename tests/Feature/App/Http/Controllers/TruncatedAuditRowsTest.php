<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Tools\RecordToolCallAuditAction;
use Domain\Conversation\Events\ToolCallCompleted;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
use Domain\Security\Actions\RecordSecurityEventAction;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/**
 * An audit row whose payload was cut at the size limit is stored as preview
 * text with an empty jsonb column. Every reader of the audit logs (the
 * governance and agent audit pages, the API audit logs and the security
 * audit viewer) shows such a row instead of failing on it.
 */
beforeEach(function (): void {
    withLicense();

    config(['chatvia.governance.max_arg_bytes' => 40, 'chatvia.security_audit.max_metadata_bytes' => 20]);

    $this->owner = User::factory()->create();
    app(CreateDefaultTeamAction::class)->execute($this->owner);
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

it('returns a cut tool-call row from the API audit log', function (Closure $uri): void {
    $this->withToken($this->owner->createToken('account')->plainTextToken)
        ->getJson($uri($this->agent))
        ->assertOk()
        ->assertJsonPath('data.0.arguments', null)
        ->assertJsonPath('data.0.arguments_truncated', true)
        ->assertJsonPath('data.0.arguments_preview', fn (string $text): bool => strlen($text) === 40);
})->with([
    'of the workspace' => [fn (): string => '/api/v1/audit'],
    'of the agent' => [fn (Agent $agent): string => "/api/v1/agents/{$agent->external_id}/audit"],
]);

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
