<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\Billing\Concerns\ReturnsToBillingPage;
use App\Identity\Workspace\Models\User;
use Domain\Billing\Data\Subscriptions\SubscribeData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Subscribe the workspace to a plan through Stripe Checkout.
 */
class SubscribeRequest extends FormRequest
{
    use ReturnsToBillingPage;

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
            'package' => ['required', 'string'],
            'yearly' => ['required', 'boolean'],
        ];
    }

    public function toData(): SubscribeData
    {
        /** @var User $user */
        $user = $this->user();

        return new SubscribeData(
            user: $user,
            package: $this->string('package')->toString(),
            yearly: $this->boolean('yearly'),
            urls: $this->checkoutUrls(),
        );
    }
}
