<?php

use Domain\Agent\Actions\Sources\AgentSourceAnalyzeFileAction;
use Domain\Knowledge\Source\File\Data\FileAnalysisData;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Domain\Knowledge\Source\File\Services\FileAnalyzer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Analysing an upload before it is trained describes the file: its page count
 * from the analyzer, its mime and file type, and whether it is audio or video.
 */
function asfaAnalyzerReturningPages(int $pages): void
{
    $analyzer = Mockery::mock(FileAnalyzer::class);
    $analyzer->shouldReceive('analyze')->andReturn(new FileAnalysisData(length: $pages));
    app()->instance(FileAnalyzer::class, $analyzer);
}

it('describes a new file', function (): void {
    asfaAnalyzerReturningPages(3);
    $file = UploadedFile::fake()->createWithContent('notes.txt', 'Fresh content');

    $result = app(AgentSourceAnalyzeFileAction::class)->execute($file);

    expect($result->length)->toBe(3)
        ->and($result->mime_type)->toBe('text/plain')
        ->and($result->filetype)->toBe(KnowledgeFileType::Text)
        ->and($result->is_audio_file)->toBeFalse()
        ->and($result->is_video_file)->toBeFalse();
});

it('marks an audio upload as audio', function (): void {
    asfaAnalyzerReturningPages(1);
    $file = UploadedFile::fake()->createWithContent('call.mp3', "ID3\x03\x00\x00\x00\x00\x00\x00".Str::repeat("\xFF\xFB\x90\x00", 64));

    $result = app(AgentSourceAnalyzeFileAction::class)->execute($file);

    expect($result->is_audio_file)->toBeTrue()
        ->and($result->is_video_file)->toBeFalse();
});
