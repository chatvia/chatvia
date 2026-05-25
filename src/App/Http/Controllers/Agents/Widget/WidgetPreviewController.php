<?php

namespace App\Http\Controllers\Agents\Widget;

use Domain\Agents\Models\Agent;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class WidgetPreviewController
{
    public function __invoke(Request $request, Agent $agent): Factory|View
    {
        return view('widget.preview', [
            'embedScriptUrl' => config('widget.script_url_embed'),
            'agentId' => $agent->external_id,
            'language' => $request->query('language', 'auto'),
        ]);
    }
}
