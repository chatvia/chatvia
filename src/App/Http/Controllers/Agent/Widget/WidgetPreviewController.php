<?php

namespace App\Http\Controllers\Agent\Widget;

use App\Http\Controllers\Agent\Concerns\RendersSurfacePreview;
use App\Identity\Workspace\Services\AgentSignInTokenService;
use Domain\Agent\Models\Agent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Renders the iframe behind the Playground's Widget preview: the real embed
 * script, signed in as the dashboard user for a protected agent (see
 * {@see RendersSurfacePreview}).
 */
class WidgetPreviewController
{
    use RendersSurfacePreview;

    public function __invoke(Request $request, Agent $agent, AgentSignInTokenService $signInTokens): View
    {
        return view('widget.preview', [
            'embedScriptUrl' => config('chatvia.widget.script_url_embed'),
            'agentId' => $agent->external_id,
            'authToken' => $this->previewToken('widget', $agent, $request->user(), $signInTokens),
            'language' => $request->query('language', 'auto'),
            'mode' => $request->query('mode') === 'bar' ? 'bar' : 'widget',
            'theme' => $this->previewTheme($request),
        ]);
    }
}
