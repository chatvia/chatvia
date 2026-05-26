<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Http\Requests\Chat\UpdateChatMemberProfileRequest;
use Domain\Auth\Actions\UpdateChatMemberProfileAction;

class ChatProfileUpdateController
{
    public function __invoke(
        UpdateChatMemberProfileRequest $request,
        UpdateChatMemberProfileAction $update,
    ): void {
        $update->execute($request->toData());
    }
}
