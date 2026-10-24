<?php

use Domain\Evaluation\Enums\ScorerType;
use Domain\Evaluation\Services\Scorers\ToolCallScorer;
use Tests\Support\Evaluation\EvalFixtures;

/**
 * The tool-call scorer checks the tool call the agent meant to make (captured,
 * never run): the expected tool by name (an MCP tool by its unprefixed name),
 * and the expected arguments as a subset of the call's, compared leniently.
 */
it('passes a call of the expected tool', function (string $expected, string $called): void {
    $result = (new ToolCallScorer)->score(
        EvalFixtures::case(ScorerType::ToolCall, ['tool' => $expected]),
        EvalFixtures::run(toolCalls: [['name' => $called, 'arguments' => '{}']]),
    );

    expect($result->pass)->toBeTrue()
        ->and($result->score)->toBe(1.0);
})->with([
    'a function by name' => ['send_email', 'send_email'],
    'an MCP tool by its unprefixed name' => ['send_message', 'mcp__slack__send_message'],
]);

it('fails and names the problem when the expected tool was not called', function (array $toolCalls, string $rationale): void {
    $result = (new ToolCallScorer)->score(EvalFixtures::case(ScorerType::ToolCall, ['tool' => 'send_email']), EvalFixtures::run('Here you go.', $toolCalls));

    expect($result->pass)->toBeFalse()
        ->and($result->rationale)->toContain($rationale);
})->with([
    'no tool' => [[], 'called no tool'],
    'another tool' => [[['name' => 'get_weather', 'arguments' => '{}']], 'get_weather'],
]);

it('passes when the expected arguments are a subset of the call, compared leniently', function (string $expected, string $sent): void {
    $result = (new ToolCallScorer)->score(
        EvalFixtures::case(ScorerType::ToolCall, ['tool' => 'send_email', 'arguments' => $expected]),
        EvalFixtures::run(toolCalls: [['name' => 'send_email', 'arguments' => $sent]]),
    );

    expect($result->pass)->toBeTrue()
        ->and($result->rationale)->toContain('matching arguments');
})->with([
    'extra arguments ignored' => ['{"to": "a@b.com"}', '{"to": "a@b.com", "subject": "Hi"}'],
    'trimmed and case-insensitive' => ['{"to": "A@B.com"}', '{"to": " a@b.com "}'],
]);

it('fails with a partial score when an argument value differs', function (): void {
    $result = (new ToolCallScorer)->score(
        EvalFixtures::case(ScorerType::ToolCall, ['tool' => 'send_email', 'arguments' => '{"to": "a@b.com", "subject": "Hi"}']),
        EvalFixtures::run(toolCalls: [['name' => 'send_email', 'arguments' => '{"to": "a@b.com", "subject": "Bye"}']]),
    );

    expect($result->pass)->toBeFalse()
        ->and($result->score)->toBe(0.5)
        ->and($result->rationale)->toContain('subject');
});

it('fails when a required argument is missing', function (): void {
    $result = (new ToolCallScorer)->score(
        EvalFixtures::case(ScorerType::ToolCall, ['tool' => 'send_email', 'arguments' => '{"to": "a@b.com"}']),
        EvalFixtures::run(toolCalls: [['name' => 'send_email', 'arguments' => '{"subject": "Hi"}']]),
    );

    expect($result->pass)->toBeFalse()
        ->and($result->rationale)->toContain('missing');
});

it('fails with a rationale when no expected tool is configured', function (): void {
    $result = (new ToolCallScorer)->score(
        EvalFixtures::case(ScorerType::ToolCall),
        EvalFixtures::run(toolCalls: [['name' => 'send_email', 'arguments' => '{}']]),
    );

    expect($result->pass)->toBeFalse()
        ->and($result->rationale)->toContain('No expected tool');
});
