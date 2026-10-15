<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Agent\Actions\Sources\AgentSourceAnalyzeFileAction;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Data\FileAnalysisData;
use Domain\Knowledge\Source\File\Services\FileAnalyzer;
use Illuminate\Http\UploadedFile;
use Support\Exceptions\DomainValidationException;

function asfaAnalyzerReturningPages(int $pages): void
{
    $analyzer = Mockery::mock(FileAnalyzer::class);
    $analyzer->shouldReceive('analyze')->andReturn(new FileAnalysisData(length: $pages));
    app()->instance(FileAnalyzer::class, $analyzer);
}

it('describes a new file', function (): void {
    asfaAnalyzerReturningPages(3);
    $user = User::factory()->workspace()->create();
    $file = UploadedFile::fake()->createWithContent('notes.txt', 'Fresh content');

    $result = app(AgentSourceAnalyzeFileAction::class)->execute($file, $user);

    expect($result->length)->toBe(3)
        ->and($result->mime_type)->toBe('text/plain')
        ->and($result->filetype)->toBe('text')
        ->and($result->is_audio_file)->toBeFalse()
        ->and($result->is_video_file)->toBeFalse();
});

it('marks an audio upload as audio', function (): void {
    asfaAnalyzerReturningPages(1);
    $user = User::factory()->workspace()->create();
    $file = UploadedFile::fake()->createWithContent('call.mp3', "ID3\x03\x00\x00\x00\x00\x00\x00".str_repeat("\xFF\xFB\x90\x00", 64));

    $result = app(AgentSourceAnalyzeFileAction::class)->execute($file, $user);

    expect($result->is_audio_file)->toBeTrue()
        ->and($result->is_video_file)->toBeFalse();
});

it('refuses a file the workspace already uploaded', function (): void {
    asfaAnalyzerReturningPages(1);
    $user = User::factory()->workspace()->create();
    $agent = Agent::factory()->create(['workspace_id' => $user->workspace()->id]);
    $file = UploadedFile::fake()->createWithContent('notes.txt', 'Same content');
    AgentFileFactory::new()->create(['workspace_id' => $user->workspace()->id, 'checksum' => md5('Same content')]);

    app(AgentSourceAnalyzeFileAction::class)->execute($file, $user, $agent);
})->throws(DomainValidationException::class, 'You have already uploaded this file.');

it('lets a duplicate file through when no agent is given', function (): void {
    asfaAnalyzerReturningPages(2);
    $user = User::factory()->workspace()->create();
    $file = UploadedFile::fake()->createWithContent('notes.txt', 'Same content');
    AgentFileFactory::new()->create(['workspace_id' => $user->workspace()->id, 'checksum' => md5('Same content')]);

    $result = app(AgentSourceAnalyzeFileAction::class)->execute($file, $user);

    expect($result->length)->toBe(2);
});
