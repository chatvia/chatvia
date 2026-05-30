<?php

namespace App\Http\Controllers\Chat\Home;

class ChatAppearanceController
{
    public function __invoke()
    {
        return current_agent()->settings('appearance');
    }
}
