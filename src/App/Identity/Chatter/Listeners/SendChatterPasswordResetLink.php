<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Listeners;

use App\Identity\Chatter\Events\ChatterPasswordResetRequested;
use App\Identity\Chatter\Notifications\ChatterResetPasswordNotification;
use App\Identity\Chatter\Services\ChatterPasswordResetTokens;
use Domain\Agent\Models\Agent;

/**
 * Emails the chatter a "reset your password" link to the reset page of one of
 * their agents' hosted chats. Nobody picks a password for the chatter, so no
 * plaintext credential is ever handed out through the dashboard. A chatter
 * without an agent cannot sign in anywhere, so they get no email.
 */
class SendChatterPasswordResetLink
{
    public function __construct(
        private readonly ChatterPasswordResetTokens $tokens,
    ) {}

    public function handle(ChatterPasswordResetRequested $event): void
    {
        $chatter = $event->chatter;
        $agent = $event->agent ?? $chatter->agents()->orderBy('agents.id')->first();

        if ($chatter->email === null || ! $agent instanceof Agent) {
            return;
        }

        $token = $this->tokens->create($chatter);

        $chatter->notify(new ChatterResetPasswordNotification($token, route('chat.password.reset', [
            'agentSlug' => $agent->slug ?? $agent->external_id,
            'token' => $token,
            'chatter' => $chatter->external_id,
        ])));
    }
}
