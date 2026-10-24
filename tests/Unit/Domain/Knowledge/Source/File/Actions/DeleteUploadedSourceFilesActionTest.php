<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Actions\DeleteUploadedSourceFilesAction;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Support\Facades\Storage;

/**
 * Discarding the agent's uploaded files: the files not trained yet go, with
 * their stored objects and agent links; trained files stay.
 */
it('discards the uploaded files with their stored objects and agent links', function (): void {
    Storage::fake();
    $agent = Agent::factory()->create();
    $uploaded = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $trained = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'status' => 'ingested']);
    $agent->files()->attach([$uploaded->id, $trained->id]);
    Storage::put($uploaded->filepath, 'bytes');

    expect(app(DeleteUploadedSourceFilesAction::class)->execute($agent))->toBe(1);

    expect(SourceFile::query()->find($uploaded->id))->toBeNull()
        ->and(SourceFile::query()->find($trained->id))->not->toBeNull()
        ->and($agent->files()->count())->toBe(1);

    Storage::assertMissing($uploaded->filepath);
});
