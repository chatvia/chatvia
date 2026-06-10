<?php

namespace App\Http\Requests\Billing;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Data\SubscribeMembersData;
use Illuminate\Foundation\Http\FormRequest;

class SubscribeMembersRequest extends FormRequest
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

    public function toData(): SubscribeMembersData
    {
        /** @var User $user */
        $user = $this->user();

        return new SubscribeMembersData(
            user: $user,
            quantity: $this->integer('quantity'),
        );
    }
}
