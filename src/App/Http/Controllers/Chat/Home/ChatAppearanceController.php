<?php

namespace App\Http\Controllers\Chat\Home;

use App\Http\Chat\HostedChatAgent;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Collection;

/**
 * The agent's appearance settings for the hosted chat, with the per-language
 * texts; the page picks the visitor's language itself.
 */
class ChatAppearanceController
{
    /**
     * @return Collection<string, mixed>
     */
    public function __invoke(#[HostedChatAgent] Agent $agent): Collection
    {
        // Raw appearance incl. the per-language translation maps; the hosted
        // chat resolves the visitor's language client-side.
        return $agent->settings('appearance');
    }
}
