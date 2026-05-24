<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ChatPdfController
{
    public function __invoke(Request $request)
    {
        return view('free-tools.chat-with-pdf');
    }
}
