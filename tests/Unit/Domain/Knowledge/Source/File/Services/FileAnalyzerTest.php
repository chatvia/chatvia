<?php

use Domain\Knowledge\Document\Conversion\OfficeText;
use Domain\Knowledge\Source\File\Data\FileAnalysisData;
use Domain\Knowledge\Source\File\Services\FileAnalyzer;
use Illuminate\Support\Str;
use Mockery\MockInterface;

/**
 * FileAnalyzer routes a file to a "length" (pages or minutes) at upload time:
 * office files are counted from their text (OfficeText is mocked here), and a
 * file that cannot be read is estimated from its size so the upload still
 * goes on. A genuinely unsupported type is rejected.
 */
it('counts the pages of an office file, or estimates them from its size when it cannot be read', function (bool $readable, int $pages): void {
    $path = tempTestFile('policy.docx', Str::repeat('x', 7000));
    $this->mock(OfficeText::class, function (MockInterface $mock) use ($path, $readable): void {
        $expectation = $mock->shouldReceive('pages')->once()->with($path, 'docx');
        $readable ? $expectation->andReturn(['One.', 'Two.', 'Three.', 'Four.', 'Five.']) : $expectation->andThrow(new RuntimeException('password protected'));
    });

    expect(app(FileAnalyzer::class)->analyze($path)->length)->toBe($pages);
})->with([
    'readable' => [true, 5],
    'unreadable' => [false, 3],
]);

it('rejects a file type that is neither OCR-supported nor media', function (): void {
    $path = tempTestFile('data.xyz', 'just some bytes');

    expect(fn (): FileAnalysisData => app(FileAnalyzer::class)->analyze($path))->toThrow(LogicException::class);
});
