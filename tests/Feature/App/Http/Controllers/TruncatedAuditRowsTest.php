<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Actions\Tools\RecordToolCallAuditAction;
use Domain\Conversation\Events\ToolCallCompleted;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/**
 * An audit row whose payload was cut at the size limit is stored as preview
 * text with an empty jsonb column. The governance audit page shows such a
 * row instead of failing on it; the other audit readers take the same
 * branch.
 */
beforeEach(function (): void {
    withLicense();

    config(['chatvia.governance.max_arg_bytes' => 40]);

    $this->owner = User::factory()->create();
    app(CreateDefaultTeamAction::class)->execute($this->owner);
    $this->agent = createAgent($this->owner);

    app(RecordToolCallAuditAction::class)->execute(new ToolCallCompleted(
        agentId: $this->agent->id,
        agentName: $this->agent->name,
        workspaceId: $this->agent->workspace_id,
        userId: null,
        userName: 'Jane',
        conversationExternalId: (string) Str::uuid7(),
        traceId: (string) Str::uuid7(),
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
