<?php

namespace App\Http\Controllers\Chat\Home;

/**
 * The agent's appearance settings for the hosted chat, with the per-language
 * texts; the page picks the visitor's language itself.
 */
class ChatAppearanceController
{
    public function __invoke()
    {
        // Raw appearance incl. the per-language translation maps; the hosted
        // chat resolves the visitor's language client-side.
        return current_agent()->settings('appearance');
    }
}
