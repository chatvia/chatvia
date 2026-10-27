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
 * policy with a per-agent-per-tool rate limit and returns one of three
 * decisions; the runner acts on each. Approval is faked here so the test
 * focuses on the composition + the rate-limit math.
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

it('returns Allow for an ungated tool within the rate limit', function (): void {
    config(['chatvia.governance.tool_rate_limit' => 60]);
    $policy = new ToolAccessPolicy(tapFakeApprovalPolicy(false), new ToolRateLimiter);

    expect($policy->decide(Agent::factory()->create(), ToolName::parse('get_weather')))
        ->toBe(ToolAccessDecision::Allow);
});

it('returns Deny once the per-tool rate limit is exhausted', function (): void {
    config(['chatvia.governance.tool_rate_limit' => 2, 'chatvia.governance.tool_rate_window_seconds' => 60]);
    $agent = Agent::factory()->create();
    $policy = new ToolAccessPolicy(tapFakeApprovalPolicy(false), new ToolRateLimiter);

    expect($policy->decide($agent, ToolName::parse('spam')))->toBe(ToolAccessDecision::Allow)   // 1st
        ->and($policy->decide($agent, ToolName::parse('spam')))->toBe(ToolAccessDecision::Allow) // 2nd
        ->and($policy->decide($agent, ToolName::parse('spam')))->toBe(ToolAccessDecision::Deny); // 3rd exceeds limit of 2
});

it('does not rate-limit a different tool on the same agent', function (): void {
    config(['chatvia.governance.tool_rate_limit' => 1]);
    $agent = Agent::factory()->create();
    $policy = new ToolAccessPolicy(tapFakeApprovalPolicy(false), new ToolRateLimiter);

    $policy->decide($agent, ToolName::parse('tool_a')); // exhausts tool_a

    expect($policy->decide($agent, ToolName::parse('tool_a')))->toBe(ToolAccessDecision::Deny)
        ->and($policy->decide($agent, ToolName::parse('tool_b')))->toBe(ToolAccessDecision::Allow);
});

it('treats a non-positive limit as unlimited', function (int $limit): void {
    config(['chatvia.governance.tool_rate_limit' => $limit]);
    $agent = Agent::factory()->create();
    $policy = new ToolAccessPolicy(tapFakeApprovalPolicy(false), new ToolRateLimiter);

    $decisions = array_map(fn (int $call): ToolAccessDecision => $policy->decide($agent, ToolName::parse('unlimited')), range(1, 5));

    expect($decisions)->each->toBe(ToolAccessDecision::Allow);
})->with(['zero' => 0, 'negative' => -1]);
