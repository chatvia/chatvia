<?php

namespace App\Http\Controllers\Agents\Starters;

use App\Models\Agent;
use Illuminate\Http\Request;

class StarterDeleteController
{
    public function __invoke(Request $request, Agent $agent, int $id)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $agent->starters()->where('id', $id)->delete();
    }
}
