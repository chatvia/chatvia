<?php

namespace App\Http\Requests\Billing;

use App\Auth\Models\User;
use Domain\Billing\Data\BillingAddressData;
use Domain\Billing\Data\UpdateBillingInformationData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBillingInformationRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'address.line1' => 'required|string|max:255',
            'address.line2' => 'nullable|string|max:255',
            'address.city' => 'required|string|max:255',
            'address.state' => 'required|string|max:255',
            'address.postal_code' => 'required|string|max:20',
            'address.country' => 'required|string|max:2',
        ];
    }

    public function toData(): UpdateBillingInformationData
    {
        /** @var User $user */
        $user = $this->user();

        return new UpdateBillingInformationData(
            user: $user,
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            address: new BillingAddressData(
                line1: $this->input('address.line1'),
                line2: $this->input('address.line2'),
                city: $this->input('address.city'),
                state: $this->input('address.state'),
                postal_code: $this->input('address.postal_code'),
                country: $this->input('address.country'),
            ),
        );
    }
}
