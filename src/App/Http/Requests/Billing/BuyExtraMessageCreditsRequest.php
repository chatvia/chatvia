<?php

namespace App\Http\Requests\Billing;

use App\Workspace\Models\User;
use Domain\Billing\Data\BuyExtraMessageCreditsData;
use Illuminate\Foundation\Http\FormRequest;

class BuyExtraMessageCreditsRequest extends FormRequest
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
        );
    }
}
