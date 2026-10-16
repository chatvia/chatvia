<?php

use Domain\Conversation\Actions\Attachments\StoreAttachmentAction;
use Domain\Conversation\Data\Attachments\UploadAttachmentData;
use Domain\Conversation\Models\Attachment;
use Domain\Knowledge\Document\Contracts\PdfInspector;
use Domain\Knowledge\Source\File\Data\FileAnalysisData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Unit coverage for the page-count capture of uploaded PDFs. The
 * upload + S3 write path is exercised end-to-end against a fake disk; the PDF
 * inspector is stubbed so the body doesn't need real pages.
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
    app()->instance(PdfInspector::class, new readonly class($pages) implements PdfInspector
    {
        public function __construct(private int $pages) {}

        public function getInfo(string $pdfPath): FileAnalysisData
        {
            return new FileAnalysisData(length: $this->pages);
        }

        public function isLocked(string $pdfPath): bool
        {
            return false;
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
    // No fake inspector bound — a non-PDF must never reach the page-count path.
    $attachment = app(StoreAttachmentAction::class)->execute(new UploadAttachmentData(
        file: staTextUpload(),
        directory: 'users/test-workspace/agents/test-agent/attachments/test',
    ));

    expect($attachment->pages)->toBeNull();
});
