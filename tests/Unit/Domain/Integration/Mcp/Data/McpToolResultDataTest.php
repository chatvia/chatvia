<?php

use Domain\Integration\Mcp\Data\McpToolResultData;

/**
 * A `tools/call` envelope splits into the text the model reads and the
 * blocks the chat UI renders. Only a first-party server's known structured
 * content becomes a block, never on an error, and never from a server
 * Chatvia does not run: a custom server could ship an ECharts option whose
 * formatter or link the chat would render as markup.
 */
it('promotes known structured content of a first-party server to blocks, but none on an error', function (): void {
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
            'image' => ['id' => 'img', 'status' => 'pending'],
            'audio' => ['id' => 'aud', 'status' => 'pending'],
            'unknown' => ['ignored' => true],
        ],
    ]);
    $failed = McpToolResultData::fromFirstPartyEnvelope([
        'content' => [['type' => 'text', 'text' => 'nope']],
        'isError' => true,
        'structuredContent' => ['form' => ['form_instance_id' => 'abc']],
    ])->toDispatchResult();

    expect($result->text)->toBe("Line one\nLine two")
        ->and($result->isError)->toBeFalse()
        ->and($result->artifacts())->toBe([
            ['kind' => 'echarts_option', 'data' => ['series' => []]],
            ['kind' => 'form', 'data' => ['form_instance_id' => 'abc']],
            ['kind' => 'calendar', 'data' => ['booking_instance_id' => 'def']],
            ['kind' => 'image', 'data' => ['id' => 'img', 'status' => 'pending']],
            ['kind' => 'audio', 'data' => ['id' => 'aud', 'status' => 'pending']],
        ])
        ->and($result->toDispatchResult()->artifacts)->toBe($result->artifacts())
        ->and($failed->text)->toBe('nope')
        ->and($failed->isError)->toBeTrue()
        ->and($failed->artifacts)->toBe([]);
});

it('takes no artifacts from a server Chatvia does not run', function (): void {
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
