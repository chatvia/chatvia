<?php

namespace App\Http\Requests\Billing;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Data\SubscribeExtraAgentsData;
use Illuminate\Foundation\Http\FormRequest;

class SubscribeExtraAgentsRequest extends FormRequest
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
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function toData(): SubscribeExtraAgentsData
    {
        /** @var User $user */
        $user = $this->user();

        return new SubscribeExtraAgentsData(
            user: $user,
            quantity: $this->integer('quantity'),
        );
    }
}
