<?php

use App\Identity\Workspace\Models\Workspace;
use Domain\Conversation\Actions\Attachments\StoreAttachmentAction;
use Domain\Conversation\Data\Attachments\UploadAttachmentData;
use Domain\Conversation\Models\Attachment;
use Domain\Knowledge\Document\Contracts\PdfInspector;
use Domain\Knowledge\Source\File\Data\FileAnalysisData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * An uploaded PDF is stored with its page count, which later caps how much
 * of it reaches the model. The PDF inspector is stubbed so the body needs no
 * real pages.
 */
beforeEach(function (): void {
    Storage::fake(Attachment::DISK);
});

/**
 * A fake upload whose content starts with the PDF magic header, so finfo
 * reports application/pdf and the action's PDF check passes. The
 * file is a temporary one PHP removes by itself.
 */
function staPdfUpload(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('doc.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");
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
        directory: 'workspaces/test-workspace/agents/test-agent/attachments/test',
        workspaceId: Workspace::factory()->create()->id,
    ));

    expect($attachment->pages)->toBe(7)
        ->and($attachment->mime_type)->toBe('application/pdf');
    Storage::disk(Attachment::DISK)->assertExists($attachment->filepath);
});
