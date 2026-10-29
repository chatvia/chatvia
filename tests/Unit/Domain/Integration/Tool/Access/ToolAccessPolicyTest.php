<?php

declare(strict_types=1);

use Domain\Agent\Models\Agent;
use Domain\Integration\Tool\Access\ToolAccessPolicy;
use Domain\Integration\Tool\Access\ToolApprovalPolicy;
use Domain\Integration\Tool\Access\ToolRateLimiter;
use Domain\Integration\Tool\Enums\ToolAccessDecision;
use Domain\Integration\Tool\ValueObjects\ToolName;

/**
 * The single dispatch-time governance gate. It composes the existing approval
 * policy with a per-agent-per-tool rate limit (a non-positive limit means
 * none) and returns one of three decisions; the runner acts on each. Approval
 * is faked here so the test focuses on the composition and the rate-limit
 * math.
 */
function tapFakeApprovalPolicy(bool $requires): ToolApprovalPolicy
{
    return new class($requires) extends ToolApprovalPolicy
    {
        public function __construct(private readonly bool $requires) {}

        public function requiresApproval(Agent $agent, ToolName $tool): bool
        {
            return $this->requires;
        }
    };
}

it('returns RequiresApproval when the approval policy gates the tool', function (): void {
    $policy = new ToolAccessPolicy(tapFakeApprovalPolicy(true), new ToolRateLimiter);

    expect($policy->decide(Agent::factory()->create(), ToolName::parse('delete_everything')))
        ->toBe(ToolAccessDecision::RequiresApproval);
});

it('returns Deny once one tool’s rate limit is exhausted, leaving other tools allowed', function (): void {
    config(['chatvia.governance.tool_rate_limit' => 2, 'chatvia.governance.tool_rate_window_seconds' => 60]);
    $agent = Agent::factory()->create();
    $policy = new ToolAccessPolicy(tapFakeApprovalPolicy(false), new ToolRateLimiter);

    $decisions = [
        $policy->decide($agent, ToolName::parse('spam')),
        $policy->decide($agent, ToolName::parse('spam')),
        $policy->decide($agent, ToolName::parse('spam')),
        $policy->decide($agent, ToolName::parse('other')),
    ];

    expect($decisions)->toBe([ToolAccessDecision::Allow, ToolAccessDecision::Allow, ToolAccessDecision::Deny, ToolAccessDecision::Allow]);
});

it('treats a non-positive limit as unlimited', function (int $limit): void {
    config(['chatvia.governance.tool_rate_limit' => $limit]);
    $agent = Agent::factory()->create();
    $policy = new ToolAccessPolicy(tapFakeApprovalPolicy(false), new ToolRateLimiter);

    $decisions = array_map(fn (int $call): ToolAccessDecision => $policy->decide($agent, ToolName::parse('unlimited')), range(1, 5));

    expect($decisions)->each->toBe(ToolAccessDecision::Allow);
})->with(['zero' => 0, 'negative' => -1]);
