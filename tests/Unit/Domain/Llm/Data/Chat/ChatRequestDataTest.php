<?php

declare(strict_types=1);

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Data\Chat\ResponseSchemaData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Models\Llm;

/**
 * The app's own tasks (guardrails, titles, rewrites...) send a body fitted to
 * the model: no temperature to a model that takes none, no longer a reply
 * than it writes, a JSON schema in the prompt for a model that cannot be held
 * to one, and the least thinking a reasoning model takes, since these tasks
 * need none.
 */
function crdPayload(Llm $model): array
{
    return new ChatRequestData($model, [ChatMessageData::user('Hi')], AiCallTag::Guardrails, temperature: 0.0)->toPayload();
}

it('asks a reasoning model for its least thinking and no temperature', function (): void {
    $payload = crdPayload(new Llm(['name' => 'gpt-6-luna', 'capabilities' => modelsDevEntry(['none', 'low', 'medium'], temperature: false)]));

    expect($payload['reasoning_effort'])->toBe('none')
        ->and($payload)->not->toHaveKey('temperature');
});

it('sends a model the catalog does not know as it is', function (): void {
    $payload = crdPayload(new Llm(['name' => 'my-server-model']));

    expect($payload['temperature'])->toBe(0.0)
        ->and($payload)->not->toHaveKey('reasoning_effort');
});

it('asks for no longer a reply than the model writes', function (): void {
    $model = new Llm(['name' => 'small-model', 'capabilities' => ['reasoning' => false, 'temperature' => true, 'limit' => ['output' => 2048]]]);

    $payload = new ChatRequestData($model, [ChatMessageData::user('Hi')], AiCallTag::ChunkEnhancement, maxTokens: 8096)->toPayload();

    expect($payload['max_tokens'])->toBe(2048);
});

it('asks for a JSON reply in the prompt when the model cannot be held to a schema', function (): void {
    $schema = new ResponseSchemaData('verdict', ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']]]);
    $request = fn (array $structuredOutput, array $messages): array => new ChatRequestData(
        new Llm(['name' => 'model', 'capabilities' => ['reasoning' => false, ...$structuredOutput]]),
        $messages,
        AiCallTag::Guardrails,
        responseSchema: $schema,
    )->toPayload();

    $held = $request(['structured_output' => true], [ChatMessageData::system('Check it.'), ChatMessageData::user('Hi')]);
    $notSaid = $request([], [ChatMessageData::user('Hi')]);
    $asked = $request(['structured_output' => false], [ChatMessageData::system('Check it.'), ChatMessageData::user('Hi')]);
    $askedAlone = $request(['structured_output' => false], [ChatMessageData::user('Hi')]);

    expect($held['response_format'])->toBe($schema->toResponseFormat())
        ->and($held['messages'][0]['content'])->toBe('Check it.')
        ->and($notSaid['response_format'])->toBe($schema->toResponseFormat())
        // Into the system prompt there is, else a system prompt of its own.
        ->and($asked)->not->toHaveKey('response_format')
        ->and($asked['messages'])->toHaveCount(2)
        ->and($asked['messages'][0]['content'])->toStartWith("Check it.\n\nReply with only a JSON value")->toContain('"ok"')
        ->and($askedAlone['messages'][0]['role'])->toBe('system')
        ->and($askedAlone['messages'][0]['content'])->toBe($schema->instruction())
        ->and($askedAlone['messages'][1])->toBe(['role' => 'user', 'content' => 'Hi']);
});
