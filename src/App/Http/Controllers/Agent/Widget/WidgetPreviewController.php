<?php

namespace App\Http\Controllers\Agent\Widget;

use App\Http\Controllers\Agent\Concerns\RendersSurfacePreview;
use App\Identity\Workspace\Services\SurfacePreviewTokens;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
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

    public function __invoke(Request $request, Agent $agent, SurfacePreviewTokens $previewTokens): View
    {
        return view('chat.widget-preview', [
            'embedScriptUrl' => url(config('chatvia.widget.script_url_embed')),
            'agentId' => $agent->external_id,
            'authToken' => $previewTokens->issue(ConversationSource::Widget->value, $agent, $request->user()),
            'language' => $request->query('language', 'auto'),
            'mode' => $request->query('mode') === ConversationSource::Bar->value ? ConversationSource::Bar->value : ConversationSource::Widget->value,
            'theme' => $this->previewTheme($request),
        ]);
    }
}
