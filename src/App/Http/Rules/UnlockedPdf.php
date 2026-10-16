<?php

declare(strict_types=1);

namespace App\Http\Rules;

use Closure;
use Domain\Knowledge\Document\Conversion\PdfInfo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * A PDF upload must open without a password: no reader can read a locked
 * one (Azure and Mistral refuse it, and so does the app), so it is refused
 * at upload with the reason, instead of failing later while it is trained.
 * Other files pass.
 */
final readonly class UnlockedPdf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! is_pdf_file($value)) {
            return;
        }

        // Resolved per use: PdfInfo is final, so tests swap a stand-in.
        if (app(PdfInfo::class)->isLocked($value->getPathname())) {
            $fail(__('This PDF is locked with a password. Remove the password and upload it again.'));
        }
    }
}
