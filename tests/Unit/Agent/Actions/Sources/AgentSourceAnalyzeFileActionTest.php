<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Agent\Actions\Sources\AgentSourceAnalyzeFileAction;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Data\FileAnalysisData;
use Domain\Knowledge\Source\File\Services\FileAnalyzer;
use Illuminate\Http\UploadedFile;
use Support\Exceptions\DomainValidationException;

function analyzerReturningPages(int $pages): void
{
    $analyzer = Mockery::mock(FileAnalyzer::class);
    $analyzer->shouldReceive('analyze')->andReturn(new FileAnalysisData(length: $pages));
    app()->instance(FileAnalyzer::class, $analyzer);
}

it('describes a new file', function (): void {
    analyzerReturningPages(3);
    $user = User::factory()->workspace()->create();
    $file = UploadedFile::fake()->createWithContent('notes.txt', 'Fresh content');

    $result = app(AgentSourceAnalyzeFileAction::class)->execute($file, $user);

    expect($result->length)->toBe(3);
});

it('refuses a file the workspace already uploaded', function (): void {
    analyzerReturningPages(1);
    $user = User::factory()->workspace()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id]);
    $file = UploadedFile::fake()->createWithContent('notes.txt', 'Same content');
    AgentFileFactory::new()->create(['user_id' => $user->id, 'checksum' => md5('Same content')]);

    app(AgentSourceAnalyzeFileAction::class)->execute($file, $user, $agent);
})->throws(DomainValidationException::class, 'You have already uploaded this file.');
