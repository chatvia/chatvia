<?php

namespace App\Http\Controllers\Billing;

use Illuminate\Http\Request;

class ResumeSubscriptionController
{
    public function __invoke(Request $request)
    {
        $request->user()->subscription()?->resume();
    }
}
