<?php

namespace App\Http\Controllers\Chat\Home;

class ChatAppearanceController
{
    public function __invoke()
    {
        // Raw appearance incl. the per-language translation maps; the hosted
        // chat resolves the visitor's language client-side.
        return current_agent()->settings('appearance');
    }
}
