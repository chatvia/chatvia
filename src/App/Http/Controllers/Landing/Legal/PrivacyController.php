<?php

namespace App\Http\Controllers\Landing\Legal;

use Illuminate\Contracts\View\View;

/**
 * Renders the public privacy policy page. It is a static view with no data.
 */
class PrivacyController
{
    public function __invoke(): View
    {
        return view('pages.policy');
    }
}
