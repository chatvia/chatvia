<?php

namespace App\Http\Controllers\Settings\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Account\UpdatePreferencesRequest;
use App\Identity\Workspace\Models\User;
use Illuminate\Http\RedirectResponse;
use Support\Enums\User\DateFormat;

class PreferencesController extends Controller
{
    /**
     * Trivial single-column preference write — a model `update()` with no rule,
     * transaction, or event, so it stays inline here per the Actions carve-out
     * in CLAUDE.md rather than behind an Action.
     */
    public function update(UpdatePreferencesRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update([
            'date_format' => $request->enum('date_format', DateFormat::class),
        ]);

        return back();
    }
}
