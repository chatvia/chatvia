<?php

namespace App\Http\Controllers\Agent\Settings;

use App\Http\Requests\Agent\Settings\UpdateAppearanceTranslationsRequest;
use Domain\Agent\Models\Agent;
use Illuminate\Http\JsonResponse;

/**
 * Saves the owner's manual per-language edits of the appearance text strings
 * (the `text_overrides` setting). Overrides win over the AI translations and are
 * never overwritten by a re-translate; a cleared field is dropped in the request
 * so it falls back to the AI output. A trivial single-setting write, so it stays
 * in the controller rather than behind an Action.
 */
class UpdateAppearanceTranslationsController
{
    public function __invoke(UpdateAppearanceTranslationsRequest $request, Agent $agent): JsonResponse
    {
        $overrides = $request->toData()->overrides;

        $agent->setSetting('text_overrides', $overrides, 'appearance');

        return response()->json(['overrides' => $overrides === [] ? (object) [] : $overrides]);
    }
}
