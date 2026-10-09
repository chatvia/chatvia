<?php

namespace App\Http\Controllers\Landing\Legal;

use Illuminate\Contracts\View\View;

/**
 * The public terms of service.
 */
class TermsController
{
    public function __invoke(): View
    {
        return view('pages.terms');
    }
}
