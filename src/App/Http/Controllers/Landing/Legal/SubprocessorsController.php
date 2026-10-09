<?php

namespace App\Http\Controllers\Landing\Legal;

use App\Http\Controllers\Landing\Legal\Concerns\RendersLegalDocument;
use Illuminate\Contracts\View\View;

/**
 * The public list of sub-processors, rendered from its compliance document.
 */
class SubprocessorsController
{
    use RendersLegalDocument;

    public function __invoke(): View
    {
        return view('landing.legal.subprocessors', $this->legalDocument('docs/compliance/gdpr/subprocessors.md'));
    }
}
