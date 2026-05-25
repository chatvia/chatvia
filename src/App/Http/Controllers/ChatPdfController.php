<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ChatPdfController
{
    public function __invoke(Request $request): Factory|View
    {
        return view('free-tools.chat-with-pdf');
    }
}
