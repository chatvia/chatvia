<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings\Account;

use App\Http\Requests\Settings\Account\UpdatePreferencesRequest;
use App\Identity\Workspace\Enums\DateFormat;
use App\Identity\Workspace\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Saves display preferences: date format and interface language.
 */
class PreferencesController
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

        $attributes = [
            'date_format' => $request->enum('date_format', DateFormat::class),
        ];

        // Only touch the locale when the form actually carries it — the Date &
        // time form omits it, so an unconditional write would wipe the user's
        // language. `auto` is normalised to null (follow the request locale).
        if ($request->has('locale')) {
            $locale = $request->input('locale');
            $attributes['locale'] = $locale === 'auto' ? null : $locale;
        }

        $user->update($attributes);

        return back();
    }
}
