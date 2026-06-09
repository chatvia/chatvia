<?php

namespace App\Http\Controllers\Landing\Legal;

use App\Http\Controllers\Landing\Legal\Concerns\RendersLegalDocument;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SubprocessorsController
{
    use RendersLegalDocument;

    public function __invoke(Request $request): Factory|View
    {
        return view(
            'landing.legal.subprocessors',
            $this->legalDocument('docs/compliance/gdpr/subprocessors.md')
        );
    }
}
