<?php

namespace Tests\Unit\Rules;

use App\Http\Rules\MaxPdfPages;
use Domain\Knowledge\Document\Conversion\PdfInfo;
use Domain\Knowledge\Source\File\Data\FileAnalysisData;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MaxPdfPagesTest extends TestCase
{
    private function pdfUpload(): UploadedFile
    {
        // A real temp file whose magic header makes finfo report application/pdf,
        // so the rule's is_pdf_file() guard passes. Page count comes from the
        // mocked PdfInfo, so the body doesn't need real pages.
        $path = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
        file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");

        return new UploadedFile($path, 'doc.pdf', 'application/pdf', null, true);
    }

    private function fakePageCount(int $pages): void
    {
        // PdfInfo is final, so swap in a stand-in under the same container key.
        // The rule only calls getInfo()->length, so duck-typing is enough.
        $this->app->instance(PdfInfo::class, new readonly class($pages)
        {
            public function __construct(private int $pages) {}

            public function getInfo(string $path): FileAnalysisData
            {
                return new FileAnalysisData(length: $this->pages);
            }
        });
    }

    public function test_it_fails_when_pdf_exceeds_the_page_limit(): void
    {
        $this->fakePageCount(11);

        $failed = false;
        new MaxPdfPages(max: 10)->validate('file', $this->pdfUpload(), function () use (&$failed): void {
            $failed = true;
        });

        $this->assertTrue($failed, 'Expected an 11-page PDF to fail a 10-page limit.');
    }

    public function test_it_passes_when_pdf_is_within_the_page_limit(): void
    {
        $this->fakePageCount(10);

        $failed = false;
        new MaxPdfPages(max: 10)->validate('file', $this->pdfUpload(), function () use (&$failed): void {
            $failed = true;
        });

        $this->assertFalse($failed, 'Expected a 10-page PDF to pass a 10-page limit.');
    }

    public function test_it_skips_non_pdf_values(): void
    {
        // No PdfInfo bound — if the rule tried to read pages this would error.
        $failed = false;
        new MaxPdfPages(max: 10)->validate('file', 'not-a-file', function () use (&$failed): void {
            $failed = true;
        });

        $this->assertFalse($failed);
    }
}
