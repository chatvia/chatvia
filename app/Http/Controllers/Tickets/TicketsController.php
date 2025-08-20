<?php

namespace App\Http\Controllers\Tickets;

use Illuminate\Http\Request;

class TicketsController
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        return $user
            ->tickets()
            ->with('replies')
            ->latest()
            ->get();
    }
}
