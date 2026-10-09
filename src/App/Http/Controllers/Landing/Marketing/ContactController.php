<?php

namespace App\Http\Controllers\Landing\Marketing;

use App\Http\Requests\Landing\ContactRequest;
use App\Marketing\Actions\SendContactMessageAction;
use App\Marketing\Services\ContactInbox;
use Illuminate\Contracts\View\View;

/**
 * Public contact page. Messages go to the install's contact inbox
 * (`chatvia.contact_email`); when none is set the form is off and the page
 * says so.
 */
class ContactController
{
    public function show(ContactInbox $inbox): View
    {
        return view('landing.contact', ['contactEnabled' => $inbox->isOpen()]);
    }

    public function submit(ContactRequest $request, SendContactMessageAction $sendMessage): View
    {
        $sent = $sendMessage->execute($request->toData());

        return view('landing.contact', $sent
            ? ['contactEnabled' => true, 'submitted' => true]
            : ['contactEnabled' => false]);
    }
}
