<?php

namespace App\Http\Controllers\Landing\FreeTools;

use Illuminate\Contracts\View\View;

/**
 * The "chat with a PDF" free-tool landing page.
 */
class ChatPdfController
{
    public function __invoke(): View
    {
        return view('free-tools.chat-with-pdf');
    }
}
