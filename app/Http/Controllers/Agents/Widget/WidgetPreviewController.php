<?php

namespace App\Http\Controllers\Agents\Widget;

use App\Models\Agent;
use Illuminate\Http\Request;

class WidgetPreviewController
{
    public function __invoke(Request $request, Agent $agent)
    {
        return view('widget.preview', [
            'embedScriptUrl' => config('widget.script_url_embed'),
            'agentId' => $agent->external_id,
        ]);
    }
}
