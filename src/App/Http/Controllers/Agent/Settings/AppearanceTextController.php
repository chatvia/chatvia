<?php

namespace App\Http\Controllers\Agent\Settings;

use App\Http\Requests\Agent\Settings\SaveAppearanceTextRequest;
use Domain\Agent\Actions\Settings\TranslateAgentAppearanceAction;
use Domain\Agent\Models\Agent;
use Illuminate\Http\JsonResponse;

/**
 * Saves the agent's appearance text strings and auto-translates them into every
 * supported language. Returns the fresh translations (plus any manual overrides)
 * so the Setup page can populate its per-language editor without a reload.
 */
class AppearanceTextController
{
    public function __invoke(
        SaveAppearanceTextRequest $request,
        Agent $agent,
        TranslateAgentAppearanceAction $action,
    ): JsonResponse {
        $translations = $action->execute($agent, $request->toData());

        $overrides = (array) ($agent->settings('appearance')->get('text_overrides') ?? []);

        return response()->json([
            'translations' => $translations->isEmpty() ? (object) [] : $translations->all(),
            'overrides' => $overrides === [] ? (object) [] : $overrides,
        ]);
    }
}
