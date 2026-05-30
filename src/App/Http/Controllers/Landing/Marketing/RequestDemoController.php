<?php

namespace App\Http\Controllers\Landing\Marketing;

use App\Marketing\Mail\RequestDemoMail;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use RyanChandler\LaravelCloudflareTurnstile\Rules\Turnstile;

class RequestDemoController
{
    public function __invoke(Request $request): Factory|View
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
                'cf-turnstile-response' => ['required', new Turnstile],
            ]);

            Mail::to('info@chatvia.ai')
                ->send(new RequestDemoMail($request->all()));

            return view('landing.request-demo', ['submitted' => true]);
        }

        return view('landing.request-demo');
    }
}
