<?php

namespace App\Http\Controllers\Landing\Legal;

use App\Http\Controllers\Landing\Legal\Concerns\RendersLegalDocument;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

class SecurityController
{
    use RendersLegalDocument;

    public function __invoke(): Factory|View
    {
        return view(
            'landing.legal.security',
            $this->legalDocument('docs/compliance/trust-center.md')
        );
    }
}
