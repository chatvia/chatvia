<?php

namespace App\Http\Controllers\Landing\Legal;

use App\Http\Controllers\Landing\Legal\Concerns\RendersLegalDocument;
use Illuminate\Contracts\View\View;

/**
 * The public security page, rendered from the trust-center document.
 */
class SecurityController
{
    use RendersLegalDocument;

    public function __invoke(): View
    {
        return view('landing.legal.security', $this->legalDocument('docs/compliance/trust-center.md'));
    }
}
