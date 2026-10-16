<?php

namespace App\Http\Controllers\Agent\Bar;

use App\Http\Controllers\Agent\Concerns\RendersSurfacePreview;
use App\Identity\Workspace\Services\SurfacePreviewTokenService;
use Domain\Agent\Models\Agent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Renders the iframe behind the Playground's Command Bar preview. The bar is a
 * cmd-K style launcher embedded into a host page, so the view ships a sample
 * landing page and mounts the real bar on top of it, signed in as the
 * dashboard user for a protected agent (see {@see RendersSurfacePreview}).
 */
class BarPreviewController
{
    use RendersSurfacePreview;

    public function __invoke(Request $request, Agent $agent, SurfacePreviewTokenService $previewTokens): View
    {
        return view('bar.preview', [
            'embedScriptUrl' => url(config('chatvia.widget.script_url_embed')),
            'agentId' => $agent->external_id,
            'agentName' => $agent->name,
            'authToken' => $previewTokens->issue('bar', $agent, $request->user()),
            'language' => $request->query('language', 'auto'),
            'theme' => $this->previewTheme($request),
        ]);
    }
}
