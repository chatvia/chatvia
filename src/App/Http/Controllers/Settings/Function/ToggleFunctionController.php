<?php

namespace App\Http\Controllers\Settings\Function;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Actions\ToggleAgentFunctionAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Flip an account-level function's `enabled` flag. Pulling this off
 * {@see FunctionController} keeps that one to standard CRUD.
 */
class ToggleFunctionController
{
    public function __invoke(Request $request, string $external_id, ToggleAgentFunctionAction $toggleFunction): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $toggleFunction->execute($user, $external_id);

        return back();
    }
}
