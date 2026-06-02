<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Http\Requests\Chat\UpdateChatterProfileRequest;
use App\Chatter\Actions\UpdateChatterProfileAction;

class ChatProfileUpdateController
{
    public function __invoke(
        UpdateChatterProfileRequest $request,
        UpdateChatterProfileAction $update,
    ): void {
        $update->execute($request->toData());
    }
}
