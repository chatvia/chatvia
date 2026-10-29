<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Ingestion\Jobs\Text\TextIngestionJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Saving a text the workspace already has is a validation error, not a
 * server error.
 */
it('answers a text the workspace already has with a validation error', function (): void {
    Bus::fake([TextIngestionJob::class]);
    Storage::fake();
    $owner = User::factory()->create();
    $content = Str::repeat('Our refund policy covers every purchase made in the last thirty days. ', 10);

    $this->actingAs($owner)
        ->postJson(route('agents.sources.text.store', createAgent($owner)), ['content' => $content])
        ->assertOk();

    $this->actingAs($owner)
        ->postJson(route('agents.sources.text.store', createAgent($owner)), ['content' => $content])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('content');
});
