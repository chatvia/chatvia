<?php

use Domain\Conversation\Actions\Attachments\StoreAttachmentAction;
use Domain\Conversation\Data\Attachments\UploadAttachmentData;
use Domain\Conversation\Models\Attachment;
use Domain\Knowledge\Document\Conversion\PdfInfo;
use Domain\Knowledge\Source\File\Data\FileAnalysisData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Unit coverage for the page-count capture of uploaded PDFs. The
 * upload + S3 write path is exercised end-to-end against a fake disk; PdfInfo is
 * stubbed so the body doesn't need real pages (same approach as MaxPdfPagesTest).
 */
beforeEach(function (): void {
    Storage::fake(Attachment::DISK);
});

/**
 * A fake upload whose content starts with the PDF magic header, so finfo
 * reports application/pdf and the action's is_pdf_file() guard passes. The
 * file is a temporary one PHP removes by itself.
 */
function staPdfUpload(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('doc.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");
}

function staTextUpload(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('notes.txt', 'just some text');
}

function staPageCount(int $pages): void
{
    // PdfInfo is final; swap a duck-typed stand-in under the same container key.
    app()->instance(PdfInfo::class, new readonly class($pages)
    {
        public function __construct(private int $pages) {}

        public function getInfo(string $path): FileAnalysisData
        {
            return new FileAnalysisData(length: $this->pages);
        }
    });
}

it('stores the page count for a PDF upload', function (): void {
    staPageCount(7);

    $attachment = app(StoreAttachmentAction::class)->execute(new UploadAttachmentData(
        file: staPdfUpload(),
        directory: 'users/test-workspace/agents/test-agent/attachments/test',
    ));

    expect($attachment->pages)->toBe(7)
        ->and($attachment->mime_type)->toBe('application/pdf');
    Storage::disk(Attachment::DISK)->assertExists($attachment->filepath);
});

it('leaves pages null for a non-PDF upload', function (): void {
    // No PdfInfo bound — a non-PDF must never reach the page-count path.
    $attachment = app(StoreAttachmentAction::class)->execute(new UploadAttachmentData(
        file: staTextUpload(),
        directory: 'users/test-workspace/agents/test-agent/attachments/test',
    ));

    expect($attachment->pages)->toBeNull();
});
