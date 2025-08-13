<?php

namespace App\Http\Controllers\Agents\Playground;

use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentPlaygroundConversationCreateController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        return [
            'id' => Str::uuid()->toString(),
        ];
    }
}
