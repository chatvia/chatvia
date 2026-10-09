<?php

namespace App\Http\Controllers\Landing\Legal;

use Illuminate\Contracts\View\View;

/**
 * The public privacy policy.
 */
class PrivacyController
{
    public function __invoke(): View
    {
        return view('pages.policy');
    }
}
