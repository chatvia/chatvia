<?php

namespace App\Http\Controllers\Agents\Agents;

use Illuminate\Http\Request;

class AgentsListController
{
    public function __invoke(Request $request)
    {
        return $request->user()->agents()
            ->withCount(['members as total_members', 'messages as total_messages'])
            ->latest()
            ->get();
    }
}
