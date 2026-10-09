<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\Billing\Concerns\ReturnsToBillingPage;
use App\Identity\Workspace\Models\User;
use Domain\Billing\Data\Account\BuyExtraMessageCreditsData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Buy extra-message packs through Stripe Checkout.
 */
class BuyExtraMessageCreditsRequest extends FormRequest
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
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toData(): BuyExtraMessageCreditsData
    {
        /** @var User $user */
        $user = $this->user();

        return new BuyExtraMessageCreditsData(
            user: $user,
            quantity: $this->integer('quantity'),
            urls: $this->checkoutUrls(),
        );
    }
}
