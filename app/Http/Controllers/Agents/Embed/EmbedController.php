<?php

namespace App\Http\Controllers\Agents\Embed;

use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmbedController
{
    public function __invoke(Request $request, Agent $agent)
    {
        return Inertia::render('agents/Embed', [
            'agent' => $agent,
            'chatUrl' => $agent->chat_url,
            'embed_settings' => $agent->settings('embed'),
        ]);
    }
}
