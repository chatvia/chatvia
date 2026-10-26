<?php

use Domain\Integration\Mcp\Data\McpToolResultData;

/**
 * A `tools/call` envelope splits into the text the model reads and the
 * blocks the chat UI renders; unknown structured content is ignored.
 */
it('joins the text parts and promotes known structured content to artifacts', function (): void {
    $result = McpToolResultData::fromFirstPartyEnvelope([
        'content' => [
            ['type' => 'text', 'text' => 'Line one'],
            ['type' => 'image', 'data' => '…'],
            ['type' => 'text', 'text' => 'Line two'],
        ],
        'structuredContent' => [
            'echarts_option' => ['series' => []],
            'form' => ['form_instance_id' => 'abc'],
            'calendar' => ['booking_instance_id' => 'def'],
            'unknown' => ['ignored' => true],
        ],
    ]);

    expect($result->text)->toBe("Line one\nLine two")
        ->and($result->isError)->toBeFalse()
        ->and($result->artifacts())->toBe([
            ['kind' => 'echarts_option', 'data' => ['series' => []]],
            ['kind' => 'form', 'data' => ['form_instance_id' => 'abc']],
            ['kind' => 'calendar', 'data' => ['booking_instance_id' => 'def']],
        ]);
});

it('falls back to the JSON envelope when there is no text part, and keeps the error flag', function (): void {
    $envelope = ['content' => [], 'isError' => true];

    $result = McpToolResultData::fromEnvelope($envelope);

    expect($result->text)->toBe(json_encode($envelope))
        ->and($result->isError)->toBeTrue()
        ->and($result->artifacts())->toBe([]);
});

it('becomes a dispatch result: text plus blocks, or a flagged error without blocks', function (): void {
    $ok = McpToolResultData::fromFirstPartyEnvelope([
        'content' => [['type' => 'text', 'text' => 'done']],
        'structuredContent' => ['form' => ['form_instance_id' => 'abc']],
    ])->toDispatchResult();

    $failed = McpToolResultData::fromFirstPartyEnvelope([
        'content' => [['type' => 'text', 'text' => 'nope']],
        'isError' => true,
        'structuredContent' => ['form' => ['form_instance_id' => 'abc']],
    ])->toDispatchResult();

    expect($ok->text)->toBe('done')
        ->and($ok->isError)->toBeFalse()
        ->and($ok->artifacts)->toBe([['kind' => 'form', 'data' => ['form_instance_id' => 'abc']]])
        ->and($failed->text)->toBe('nope')
        ->and($failed->isError)->toBeTrue()
        ->and($failed->artifacts)->toBe([]);
});

it('takes no artifacts from a server Chatvia does not run', function (): void {
    // A custom server could ship an ECharts option whose formatter or link
    // the chat would render as markup.
    $result = McpToolResultData::fromEnvelope([
        'content' => [['type' => 'text', 'text' => 'chart']],
        'structuredContent' => [
            'echarts_option' => ['tooltip' => ['formatter' => '<img src=x onerror=alert(1)>']],
            'form' => ['form_instance_id' => 'abc'],
        ],
    ]);

    expect($result->artifacts())->toBe([])
        ->and($result->toDispatchResult()->artifacts)->toBe([])
        ->and($result->text)->toBe('chart');
});
