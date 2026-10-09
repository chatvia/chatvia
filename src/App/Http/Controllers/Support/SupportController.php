<?php

namespace App\Http\Controllers\Support;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The Support page, where a workspace opens and follows its helpdesk tickets.
 */
class SupportController
{
    public function __invoke(): Response
    {
        return Inertia::render('Support');
    }
}
