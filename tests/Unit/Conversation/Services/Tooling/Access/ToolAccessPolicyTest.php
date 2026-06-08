<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Tooling\Access\ToolAccessDecision;
use Domain\Conversation\Services\Tooling\Access\ToolAccessPolicy;
use Domain\Conversation\Services\Tooling\Access\ToolRateLimiter;
use Domain\Conversation\Services\Tooling\Approval\ToolApprovalPolicy;

/**
 * The single dispatch-time governance gate. It composes the existing approval
 * policy with a per-agent-per-tool rate limit and returns one of three
 * decisions; the runner acts on each. Approval is faked here so the test
 * focuses on the composition + the rate-limit math.
 */
function tapFakeApprovalPolicy(bool $requires): ToolApprovalPolicy
{
    return new class($requires) extends ToolApprovalPolicy
    {
        public function __construct(private bool $requires) {}

        public function requiresApproval(Agent $agent, string $toolName): bool
        {
            return $this->requires;
        }
    };
}

it('returns RequiresApproval when the approval policy gates the tool', function (): void {
    $policy = new ToolAccessPolicy(tapFakeApprovalPolicy(true), new ToolRateLimiter);

    expect($policy->decide(Agent::factory()->create(), 'delete_everything'))
        ->toBe(ToolAccessDecision::RequiresApproval);
});

it('returns Allow for an ungated tool within the rate limit', function (): void {
    config(['chatvia.governance.tool_rate_limit' => 60]);
    $policy = new ToolAccessPolicy(tapFakeApprovalPolicy(false), new ToolRateLimiter);

    expect($policy->decide(Agent::factory()->create(), 'get_weather'))
        ->toBe(ToolAccessDecision::Allow);
});

it('returns Deny once the per-tool rate limit is exhausted', function (): void {
    config(['chatvia.governance.tool_rate_limit' => 2, 'chatvia.governance.tool_rate_window_seconds' => 60]);
    $agent = Agent::factory()->create();
    $policy = new ToolAccessPolicy(tapFakeApprovalPolicy(false), new ToolRateLimiter);

    expect($policy->decide($agent, 'spam'))->toBe(ToolAccessDecision::Allow)   // 1st
        ->and($policy->decide($agent, 'spam'))->toBe(ToolAccessDecision::Allow) // 2nd
        ->and($policy->decide($agent, 'spam'))->toBe(ToolAccessDecision::Deny); // 3rd exceeds limit of 2
});

it('does not rate-limit a different tool on the same agent', function (): void {
    config(['chatvia.governance.tool_rate_limit' => 1]);
    $agent = Agent::factory()->create();
    $policy = new ToolAccessPolicy(tapFakeApprovalPolicy(false), new ToolRateLimiter);

    $policy->decide($agent, 'tool_a'); // exhausts tool_a

    expect($policy->decide($agent, 'tool_a'))->toBe(ToolAccessDecision::Deny)
        ->and($policy->decide($agent, 'tool_b'))->toBe(ToolAccessDecision::Allow);
});

it('treats a non-positive limit as unlimited', function (): void {
    config(['chatvia.governance.tool_rate_limit' => 0]);
    $agent = Agent::factory()->create();
    $policy = new ToolAccessPolicy(tapFakeApprovalPolicy(false), new ToolRateLimiter);

    foreach (range(1, 5) as $ignored) {
        expect($policy->decide($agent, 'unlimited'))->toBe(ToolAccessDecision::Allow);
    }
});
