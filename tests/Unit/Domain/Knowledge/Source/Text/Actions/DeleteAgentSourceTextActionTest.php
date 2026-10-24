<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Text\Actions\CreateAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Actions\DeleteAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

/**
 * Deleting a text source removes its row, its chunks (the summary included),
 * its agent link and its folder in storage.
 */
it('deletes a text with its chunks, summary included, its agent link and its folder', function (): void {
    Storage::fake();
    Bus::fake();
    $agent = createAgent(User::factory()->create());
    $text = app(CreateAgentSourceTextAction::class)->execute($agent, 'The original text about our product.');
    Chunk::factory()->forSource($text)->summary()->create();
    Chunk::factory()->forSource($text)->create();

    app(DeleteAgentSourceTextAction::class)->execute($agent, $text);

    expect(SourceText::query()->find($text->id))->toBeNull()
        ->and(Chunk::query()->ofSource($text)->exists())->toBeFalse()
        ->and($agent->texts()->count())->toBe(0)
        ->and(Storage::allFiles())->toBe([]);
});
