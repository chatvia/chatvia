<?php

namespace App\Http\Rules;

use App\Identity\Workspace\Models\User;
use Closure;
use Domain\Billing\Contracts\Entitlements;
use Domain\Knowledge\Document\Conversion\PdfInfo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Blocks a PDF upload when the workspace can't afford to chat with it. A
 * document-chat turn costs one message per page on top of the base turn message
 * (see ConsumeMessageCreditsAction), so a PDF with more pages than the
 * workspace's remaining balance could never be used — we reject it at upload
 * with a clear message instead of letting the send fail or the balance go
 * negative.
 *
 * Non-PDF values are skipped (the mimes/mimetypes rules own that). A PDF that
 * `pdfinfo` can't read fails closed; an unresolvable workspace fails open so a
 * lookup miss never blocks a legitimate upload.
 */
final readonly class HasMessageCreditsForPdf implements ValidationRule
{
    public function __construct(
        private Entitlements $entitlements,
        private ?int $workspaceUserId,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! is_pdf_file($value)) {
            return;
        }

        $workspace = $this->workspaceUserId ? User::find($this->workspaceUserId) : null;

        // Fail open: a missing workspace is a lookup miss, not an over-quota
        // upload — don't block it here (the send-time credit gate still applies).
        if (! $workspace) {
            return;
        }

        try {
            // Resolved per use: PdfInfo is final, so tests swap a stand-in
            // into the container instead of passing one in.
            $pages = app(PdfInfo::class)->getInfo($value->getPathname())->length;
        } catch (Throwable) {
            $fail(__('We couldn\'t read this PDF. Please try a different file.'));

            return;
        }

        // Null means no limit (billing is turned off).
        $available = $this->entitlements->availableMessages($workspace);

        // The turn costs 1 (base) + pages, so available must exceed pages for at
        // least one full document-chat turn to be possible.
        if ($available !== null && $available <= $pages) {
            $fail(__('This PDF has :pages pages and uses :pages extra messages per question, but you only have :available messages left.', [
                'pages' => $pages,
                'available' => $available,
            ]));
        }
    }
}
