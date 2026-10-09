<?php

namespace App\Http\Controllers\Landing\Legal;

use Illuminate\Contracts\View\View;

/**
 * Renders the public refund policy page. It is a static view with no data.
 */
class RefundController
{
    public function __invoke(): View
    {
        return view('pages.refund');
    }
}
