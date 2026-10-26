<?php

declare(strict_types=1);

use Domain\Conversation\Services\Tooling\Routing\UnavailableToolFilter;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Enums\ToolKind;
use Domain\Llm\Operations\Media\NullSpeaker;
use Domain\Llm\Services\AudioGeneration;
use Tests\Support\Llm\FakeImageGenerator;
use Tests\Support\Llm\FakeSpeaker;

/**
 * The tools of a capability whose service an admin has not set up are never
 * offered to the model; every other tool stays.
 */
function utfEntries(): array
{
    return array_map(
        fn (string $name): ToolEntry => new ToolEntry(kind: ToolKind::Mcp, sourceId: 'x:'.$name, name: $name, description: '', parameters: []),
        ['mcp__image_generation__generate-image', 'mcp__audio_generation__generate-audio', 'mcp__charts__render-chart'],
    );
}

function utfNames(array $entries): array
{
    return array_map(fn (ToolEntry $entry): string => $entry->name, $entries);
}

it('keeps every tool while the services are set up', function (): void {
    FakeImageGenerator::install();
    app()->instance(AudioGeneration::class, new AudioGeneration(new FakeSpeaker));

    expect(utfNames(app(UnavailableToolFilter::class)->apply(utfEntries())))->toBe(utfNames(utfEntries()));
});

it('drops the tools of a capability whose service is off', function (): void {
    FakeImageGenerator::install(enabled: false);
    app()->instance(AudioGeneration::class, new AudioGeneration(new NullSpeaker));

    expect(utfNames(app(UnavailableToolFilter::class)->apply(utfEntries())))->toBe(['mcp__charts__render-chart']);
});
