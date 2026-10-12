<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat\Auth;

use App\Http\Chat\HostedChatAgent;
use App\Http\Requests\Chat\ResetChatterPasswordRequest;
use App\Identity\Chatter\Actions\ResetChatterPasswordWithTokenAction;
use Domain\Agent\Models\Agent;
use Illuminate\Http\RedirectResponse;

/**
 * Saves a chatter's new password from the hosted chat's reset page, then
 * sends them to the chat's sign-in page.
 */
class ChatResetPasswordController
{
    public function __invoke(
        ResetChatterPasswordRequest $request,
        #[HostedChatAgent] Agent $agent,
        ResetChatterPasswordWithTokenAction $reset,
    ): RedirectResponse {
        $reset->execute($request->toData($agent));

        return to_route('chat.login.show')->with('status', trans('passwords.reset'));
    }
}
