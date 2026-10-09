<?php

namespace App\Http\Requests\Billing;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Data\Account\PaymentMethodData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Picks one of the workspace's saved payment methods (to delete it or make it
 * the default).
 */
class PaymentMethodIdRequest extends FormRequest
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
            'payment_method_id' => ['required', 'string'],
        ];
    }

    public function toData(): PaymentMethodData
    {
        /** @var User $user */
        $user = $this->user();

        return new PaymentMethodData(
            user: $user,
            paymentMethodId: $this->string('payment_method_id')->toString(),
        );
    }
}
