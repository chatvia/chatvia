<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use Illuminate\Http\Request;

/**
 * Where Stripe returns a failed plan checkout to. Nothing to do; the page
 * renders empty.
 */
class BillingSubscribeFailureController
{
    public function __invoke(Request $request) {}
}
