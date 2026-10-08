<?php

namespace App\Http\Controllers\Landing\Marketing;

use App\Http\Requests\Landing\ContactRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Support\Cms\Marketing\Mail\ContactMail;

/**
 * Public contact page. Messages go to `chatvia.contact_email`; when that is
 * not set, the form is turned off and the page says so.
 */
class ContactController
{
    public function show(): View
    {
        return view('landing.contact', ['contactEnabled' => $this->recipient() !== null]);
    }

    public function submit(ContactRequest $request): View
    {
        $recipient = $this->recipient();

        if ($recipient === null) {
            return view('landing.contact', ['contactEnabled' => false]);
        }

        Mail::to($recipient)->send(new ContactMail($request->validated()));

        return view('landing.contact', ['contactEnabled' => true, 'submitted' => true]);
    }

    private function recipient(): ?string
    {
        $email = config('chatvia.contact_email');

        return is_string($email) && $email !== '' ? $email : null;
    }
}
