<?php

use Domain\Conversation\Services\Tooling\Dispatcher\ToolDispatchResult;

/**
 * The placement note is what makes a chart/form land where the model intends
 * rather than always above the message. The chat renderer
 * (AssistantMessageBody.vue) splits the reply on own-line `![chart]` / `![form]`
 * markers, so those exact marker strings are a cross-language contract — these
 * tests guard them.
 */
it('leaves text untouched when there are no artifacts', function (): void {
    $result = ToolDispatchResult::withArtifacts('Plain tool output.', []);

    expect($result->text)->toBe('Plain tool output.')
        ->and($result->artifacts)->toBe([])
        ->and($result->isError)->toBeFalse();
});

it('appends a chart placement note when a chart artifact is present', function (): void {
    $artifacts = [['kind' => 'echarts_option', 'data' => ['series' => []]]];

    $result = ToolDispatchResult::withArtifacts('Five-year trend.', $artifacts);

    expect($result->text)
        ->toContain('Five-year trend.')
        ->toContain('![chart]')
        ->toContain('on its own line')
        ->toContain('does not see this message')
        ->and($result->artifacts)->toBe($artifacts)
        ->and($result->isError)->toBeFalse();
});

it('uses the form marker for a form artifact', function (): void {
    $result = ToolDispatchResult::withArtifacts('Here is a form.', [
        ['kind' => 'form', 'data' => ['schema' => []]],
    ]);

    expect($result->text)
        ->toContain('![form]')
        ->not->toContain('![chart]');
});

it('emits one ordered marker per artifact when several are rendered', function (): void {
    $result = ToolDispatchResult::withArtifacts('Two visuals.', [
        ['kind' => 'echarts_option', 'data' => []],
        ['kind' => 'form', 'data' => []],
    ]);

    expect($result->text)
        ->toContain('![chart], then ![form]')
        ->toContain('Place the 2 markers in this order');
});

it('never adds a note to plain text or error results', function (): void {
    expect(ToolDispatchResult::text('hi')->text)->toBe('hi')
        ->and(ToolDispatchResult::error('boom')->text)->toBe('boom')
        ->and(ToolDispatchResult::error('boom')->isError)->toBeTrue();
});
