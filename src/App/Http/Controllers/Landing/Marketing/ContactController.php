<?php

namespace App\Http\Controllers\Landing\Marketing;

use App\Http\Requests\Landing\ContactRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Support\Cms\Marketing\Mail\ContactMail;

class ContactController
{
    public function show(): View
    {
        return view('landing.contact');
    }

    public function submit(ContactRequest $request): View
    {
        Mail::to('info@chatvia.ai')->send(new ContactMail($request->validated()));

        return view('landing.contact', ['submitted' => true]);
    }
}
