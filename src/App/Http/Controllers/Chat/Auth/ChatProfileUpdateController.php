<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Http\Requests\Chat\UpdateChatterProfileRequest;
use App\Identity\Chatter\Actions\UpdateChatterProfileAction;
use Illuminate\Http\RedirectResponse;

class ChatProfileUpdateController
{
    public function __invoke(
        UpdateChatterProfileRequest $request,
        UpdateChatterProfileAction $update,
    ): RedirectResponse {
        $update->execute($request->toData());

        return back();
    }
}
