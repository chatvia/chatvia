<?php

namespace App\Http\Rules;

use Closure;
use Domain\Knowledge\Document\Conversion\PdfInfo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Caps how many pages a single chat-attachment PDF may have. The whole document
 * is sent to the document LLM on every question, so an unbounded page count means unbounded
 * cost and latency — this fails the `file` field with a 422 when the upload
 * exceeds `agent.files.upload.max_pdf_pages`.
 *
 * Non-PDF values are skipped (the `mimes`/`mimetypes` rules own that check). A
 * PDF that `pdfinfo` can't read fails closed with a generic message rather than
 * bubbling a 500.
 */
final readonly class MaxPdfPages implements ValidationRule
{
    public function __construct(
        private int $max,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! is_pdf_file($value)) {
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

        if ($pages > $this->max) {
            $fail(__('This PDF has :pages pages — the limit is :max.', ['pages' => $pages, 'max' => $this->max]));
        }
    }
}
