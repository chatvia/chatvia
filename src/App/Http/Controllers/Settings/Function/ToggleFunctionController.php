<?php

namespace App\Http\Controllers\Settings\Function;

use App\Workspace\Models\User;
use App\Http\Controllers\Controller;
use Domain\Function\Actions\ToggleAgentFunctionAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Flip an account-level function's `enabled` flag. Pulling this off
 * {@see FunctionController} keeps that one to standard CRUD.
 */
class ToggleFunctionController extends Controller
{
    public function __invoke(Request $request, string $external_id, ToggleAgentFunctionAction $toggleFunction): RedirectResponse
    {
        abort_unless(has_team_permission('account.functions'), 404);

        /** @var User $user */
        $user = $request->user();

        $toggleFunction->execute($user, $external_id);

        return back();
    }
}
