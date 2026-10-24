<?php

use App\Admin\Settings\AdminSettings;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\KnowledgeSettings;
use App\Admin\Settings\MailSettings;

/**
 * What a settings class makes of typed input (the panel's fields, the
 * `chatvia:settings:set` command): the property's type, and null for blank
 * or invalid input, so a blank field keeps the config default.
 */
it('casts input to the property type and turns blank or invalid input into null', function (string $class, string $property, mixed $input, mixed $expected): void {
    /** @var class-string<AdminSettings> $class */
    expect($class::cast($property, $input))->toBe($expected);
})->with([
    'trimmed text' => [MailSettings::class, 'smtp_host', '  smtp.example.com ', 'smtp.example.com'],
    'blank text' => [MailSettings::class, 'smtp_host', '   ', null],
    'integer' => [MailSettings::class, 'smtp_port', '587', 587],
    'not an integer' => [MailSettings::class, 'smtp_port', 'abc', null],
    'float' => [KnowledgeSettings::class, 'relevance_threshold', '0.35', 0.35],
    'toggle off' => [KnowledgeSettings::class, 'hybrid_search', '0', false],
    'toggle on' => [KnowledgeSettings::class, 'hybrid_search', true, true],
    'select' => [AiSettings::class, 'reranker', 'cohere', 'cohere'],
]);
