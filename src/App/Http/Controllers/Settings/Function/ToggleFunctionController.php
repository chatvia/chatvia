<?php

namespace App\Http\Controllers\Settings\Function;

use App\Identity\Workspace\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Flip a workspace function's on/off switch.
 */
class ToggleFunctionController
{
    public function __invoke(Request $request, string $external_id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->workspace()->functions()->where('external_id', $external_id)->firstOrFail()->toggleEnabled();

        return back();
    }
}
