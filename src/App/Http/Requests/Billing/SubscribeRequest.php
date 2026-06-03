<?php

namespace App\Http\Requests\Billing;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Data\SubscribeData;
use Illuminate\Foundation\Http\FormRequest;

class SubscribeRequest extends FormRequest
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
        );
    }
}
