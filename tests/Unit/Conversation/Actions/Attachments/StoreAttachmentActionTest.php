<?php

use Domain\Conversation\Actions\Attachments\StoreAttachmentAction;
use Domain\Conversation\Data\Attachments\UploadAttachmentData;
use Domain\Knowledge\Document\PdfInfo;
use Domain\Knowledge\Source\File\Data\FileAnalysisData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Unit coverage for the page-count capture added for page-based billing. The
 * upload + S3 write path is exercised end-to-end against a fake disk; PdfInfo is
 * stubbed so the body doesn't need real pages (same approach as MaxPdfPagesTest).
 */
beforeEach(fn () => Storage::fake('public'));

function realPdfUpload(): UploadedFile
{
    // A real temp file whose magic header makes finfo report application/pdf,
    // so the action's is_pdf_file() guard passes.
    $path = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
    file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");

    return new UploadedFile($path, 'doc.pdf', 'application/pdf', null, true);
}

function realTextUpload(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'txt').'.txt';
    file_put_contents($path, 'just some text');

    return new UploadedFile($path, 'notes.txt', 'text/plain', null, true);
}

function fakePageCount(int $pages): void
{
    // PdfInfo is final; swap a duck-typed stand-in under the same container key.
    app()->instance(PdfInfo::class, new class($pages)
    {
        public function __construct(private int $pages) {}

        public function getInfo(string $path): FileAnalysisData
        {
            return new FileAnalysisData(length: $this->pages);
        }
    });
}

it('stores the page count for a PDF upload', function (): void {
    fakePageCount(7);

    $attachment = app(StoreAttachmentAction::class)->execute(new UploadAttachmentData(
        file: realPdfUpload(),
        directory: 'chat-uploads/agent-1/test',
    ));

    expect($attachment->pages)->toBe(7)
        ->and($attachment->mime_type)->toBe('application/pdf');
    Storage::disk('public')->assertExists($attachment->filepath);
});

it('leaves pages null for a non-PDF upload', function (): void {
    // No PdfInfo bound — a non-PDF must never reach the page-count path.
    $attachment = app(StoreAttachmentAction::class)->execute(new UploadAttachmentData(
        file: realTextUpload(),
        directory: 'chat-uploads/agent-1/test',
    ));

    expect($attachment->pages)->toBeNull();
});
