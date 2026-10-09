<?php

use Domain\Llm\Services\LlmJson;

/**
 * Models asked for JSON sometimes wrap it in a code fence or a sentence; the
 * decoder still finds it, and returns null when there is none.
 */
it('decodes JSON wrapped the ways models wrap it', function (?string $reply, ?array $expected): void {
    expect(LlmJson::decode($reply))->toBe($expected);
})->with([
    'plain object' => ['{"a":1}', ['a' => 1]],
    'plain list' => ['["x","y"]', ['x', 'y']],
    'code fence' => ["```json\n{\"a\":1}\n```", ['a' => 1]],
    'prose around an object' => ['Sure! {"a": {"b": 2}} Hope it helps.', ['a' => ['b' => 2]]],
    'prose around a list' => ['Here: ["x"] done', ['x']],
    'no json' => ['I cannot help with that.', null],
    'empty' => ['', null],
    'null' => [null, null],
    'scalar' => ['42', null],
]);
