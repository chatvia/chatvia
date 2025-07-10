<?php

namespace App\Http\Controllers\Agents\Starters;

use App\Models\Agent;
use Illuminate\Http\Request;

class StartersStoreController
{
    public function __invoke(Request $request, Agent $agent)
    {
        $request->validate([
            'text' => ['required', 'string', 'max:255'],
        ]);

        return $agent->starters()->create(['text' => $request->input('text')]);
    }
}
