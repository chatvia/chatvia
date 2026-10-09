<?php

namespace App\Http\Controllers\Landing\Legal;

use Illuminate\Contracts\View\View;

/**
 * The public refund policy.
 */
class RefundController
{
    public function __invoke(): View
    {
        return view('pages.refund');
    }
}
