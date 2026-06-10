<?php

namespace App\Http\Controllers\Landing\Marketing;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use RyanChandler\LaravelCloudflareTurnstile\Rules\Turnstile;
use Support\Cms\Marketing\Mail\ContactMail;

class ContactController
{
    public function __invoke(Request $request): Factory|View
    {
        if ($request->method() === 'POST') {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'company' => ['nullable', 'string', 'max:255'],
                'message' => ['required', 'string', 'max:5000'],
                'cf-turnstile-response' => ['required', new Turnstile],
            ]);

            Mail::to('info@chatvia.ai')->send(new ContactMail($validated));

            return view('landing.contact', ['submitted' => true]);
        }

        return view('landing.contact');
    }
}
