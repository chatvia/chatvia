<?php

namespace App\Http\Controllers;

use App\Mail\RequestDemoMail;
use Coderflex\LaravelTurnstile\Rules\TurnstileCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class RequestDemoController
{
    public function __invoke(Request $request)
    {
        if ($request->method() === 'POST') {
            $request->validate([
                'name' => ['required', 'string'],
                'email' => ['required', 'email'],
                'website' => ['required', 'url'],
                'city' => ['required', 'string'],
                'postal_code' => ['required', 'string'],
                'country' => ['required', 'string'],
                'usage_timeline' => ['required', 'string'],
                'cf-turnstile-response' => [new TurnstileCheck],
            ]);

            Mail::to('info@chatvia.ai')
                ->send(new RequestDemoMail($request->all()));

            return view('landing.request-demo', ['submitted' => true]);
        }

        return view('landing.request-demo');
    }
}
