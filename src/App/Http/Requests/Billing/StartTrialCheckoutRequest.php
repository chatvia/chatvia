<?php

namespace App\Http\Requests\Billing;

use Domain\Billing\Data\Stripe\CheckoutUrlsData;
use Domain\Billing\Data\Trials\StartTrialCheckoutData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Start the public trial checkout. Stripe replaces `{CHECKOUT_SESSION_ID}` in
 * the success URL, so the success page can find the account the checkout
 * created.
 */
class StartTrialCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'package' => ['required', 'string', 'in:starter,standard,pro'],
            'yearly' => ['required', 'boolean'],
        ];
    }

    public function toData(): StartTrialCheckoutData
    {
        return new StartTrialCheckoutData(
            package: $this->string('package')->toString(),
            yearly: $this->boolean('yearly'),
            userIp: (string) $this->ip(),
            urls: new CheckoutUrlsData(
                successUrl: route('trial.success').'?session_id={CHECKOUT_SESSION_ID}',
                cancelUrl: route('home').'#pricing',
            ),
        );
    }
}
