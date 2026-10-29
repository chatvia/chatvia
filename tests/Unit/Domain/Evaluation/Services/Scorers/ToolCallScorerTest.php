<?php

use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Services\Scorers\ToolCallScorer;
use Tests\Support\Evaluation\EvalFixtures;

/**
 * The tool-call scorer checks the tool call the agent meant to make (captured,
 * never run): the expected tool by name (an MCP tool by its unprefixed name),
 * and the expected arguments as a subset of the call's, compared leniently.
 */
it('passes a call of the expected tool with the expected arguments as a lenient subset', function (array $config, string $called, string $sent): void {
    $result = (new ToolCallScorer)->score(
        EvalFixtures::case(ScorerType::ToolCall, $config),
        EvalFixtures::run(toolCalls: [['name' => $called, 'arguments' => $sent]]),
    );

    expect($result->pass)->toBeTrue()
        ->and($result->score)->toBe(1.0);
})->with([
    'a function by name' => [['tool' => 'send_email'], 'send_email', '{}'],
    'an MCP tool by its unprefixed name' => [['tool' => 'send_message'], 'mcp__slack__send_message', '{}'],
    'extra arguments ignored' => [['tool' => 'send_email', 'arguments' => '{"to": "a@b.com"}'], 'send_email', '{"to": "a@b.com", "subject": "Hi"}'],
    'trimmed and case-insensitive' => [['tool' => 'send_email', 'arguments' => '{"to": "A@B.com"}'], 'send_email', '{"to": " a@b.com "}'],
]);

it('fails with a partial score when an argument value differs', function (): void {
    $result = (new ToolCallScorer)->score(
        EvalFixtures::case(ScorerType::ToolCall, ['tool' => 'send_email', 'arguments' => '{"to": "a@b.com", "subject": "Hi"}']),
        EvalFixtures::run(toolCalls: [['name' => 'send_email', 'arguments' => '{"to": "a@b.com", "subject": "Bye"}']]),
    );

    expect($result->pass)->toBeFalse()
        ->and($result->score)->toBe(0.5);
});
