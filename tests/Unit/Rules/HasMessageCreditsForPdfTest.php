<?php

use App\Http\Rules\HasMessageCreditsForPdf;
use App\Identity\Workspace\Models\User;
use Domain\Billing\Contracts\Entitlements;
use Domain\Billing\Models\UserCredit;
use Domain\Knowledge\Document\Conversion\PdfInfo;
use Domain\Knowledge\Source\File\Data\FileAnalysisData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::flush());

function rulePdfUpload(): UploadedFile
{
    // Real temp file with a PDF magic header so the rule's is_pdf_file() guard
    // passes; the page count comes from the stubbed PdfInfo.
    $path = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
    file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");

    return new UploadedFile($path, 'doc.pdf', 'application/pdf', null, true);
}

function stubPdfPages(int $pages): void
{
    app()->instance(PdfInfo::class, new class($pages)
    {
        public function __construct(private int $pages) {}

        public function getInfo(string $path): FileAnalysisData
        {
            return new FileAnalysisData(length: $this->pages);
        }
    });
}

function workspaceWithMessages(int $messages): User
{
    $user = User::factory()->workspace()->create();
    $credit = UserCredit::factory()->create([
        'user_id' => $user->id,
        'messages' => $messages,
        'started_at' => now()->subDay(),
        'ends_at' => now()->addDays(29),
    ]);
    $credit->usage()->create(['messages' => 0]);

    return $user;
}

function runCreditsRule(?int $workspaceUserId, mixed $value): bool
{
    $failed = false;
    (new HasMessageCreditsForPdf(app(Entitlements::class), $workspaceUserId))->validate('file', $value, function () use (&$failed): void {
        $failed = true;
    });

    return $failed;
}

it('passes when the workspace has more messages than the PDF has pages', function (): void {
    stubPdfPages(10);
    $user = workspaceWithMessages(100);

    expect(runCreditsRule($user->id, rulePdfUpload()))->toBeFalse();
});

it('fails when remaining messages cannot cover the pages', function (): void {
    stubPdfPages(10);
    $user = workspaceWithMessages(10); // available 10 <= 10 pages → block

    expect(runCreditsRule($user->id, rulePdfUpload()))->toBeTrue();
});

it('fails open when the workspace cannot be resolved', function (): void {
    // No PdfInfo stub — a null workspace must short-circuit before reading pages.
    expect(runCreditsRule(null, rulePdfUpload()))->toBeFalse();
});

it('skips non-PDF values', function (): void {
    expect(runCreditsRule(workspaceWithMessages(1)->id, 'not-a-file'))->toBeFalse();
});
