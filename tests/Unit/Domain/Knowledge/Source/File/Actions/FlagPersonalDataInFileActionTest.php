<?php

use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\File\Actions\FlagPersonalDataInFileAction;

/**
 * A trained file is checked for card numbers, IBANs and US SSNs; the kinds
 * found and the number of parts holding them are noted in its metadata.
 * E-mail addresses do not count, and a clean file loses an old note.
 */
it('notes the kinds of identifier found and in how many parts', function (): void {
    $file = AgentFileFactory::new()->create(['metadata' => ['pages' => 3]]);
    Chunk::factory()->forSource($file)->create(['chunk' => 'Pay with card 4111 1111 1111 1111 at the desk.']);
    Chunk::factory()->forSource($file)->create(['chunk' => 'Our account: DE89 3704 0044 0532 0130 00.']);
    Chunk::factory()->forSource($file)->create(['chunk' => 'Write to help@example.com any time.']);

    app(FlagPersonalDataInFileAction::class)->execute($file);

    $metadata = $file->fresh()->metadata;

    expect($metadata['pages'])->toBe(3)
        ->and($metadata['personal_data']['parts'])->toBe(2)
        ->and($metadata['personal_data']['types'])->toEqualCanonicalizing(['credit_card', 'iban']);
});

it('clears an old note when the file holds none any more', function (): void {
    $file = AgentFileFactory::new()->create(['metadata' => ['personal_data' => ['types' => ['iban'], 'parts' => 1]]]);
    Chunk::factory()->forSource($file)->create(['chunk' => 'Opening hours: nine to five.']);

    app(FlagPersonalDataInFileAction::class)->execute($file);

    expect($file->fresh()->metadata)->not->toHaveKey('personal_data');
});
