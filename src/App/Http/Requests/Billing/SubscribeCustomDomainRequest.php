<?php

namespace App\Http\Requests\Billing;

use App\Workspace\Models\User;
use Domain\Billing\Data\SubscribeCustomDomainData;
use Illuminate\Foundation\Http\FormRequest;

class SubscribeCustomDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function toData(): SubscribeCustomDomainData
    {
        /** @var User $user */
        $user = $this->user();

        return new SubscribeCustomDomainData(
            user: $user,
            quantity: $this->integer('quantity'),
        );
    }
}
