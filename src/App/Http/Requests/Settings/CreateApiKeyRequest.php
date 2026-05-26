<?php

namespace App\Http\Requests\Settings;

use App\Auth\Models\User;
use Domain\Auth\Data\CreateApiKeyData;
use Illuminate\Foundation\Http\FormRequest;

class CreateApiKeyRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:3', 'max:64'],
        ];
    }

    public function toData(): CreateApiKeyData
    {
        /** @var User $user */
        $user = $this->user();

        return new CreateApiKeyData(
            user: $user,
            name: $this->string('name')->toString(),
        );
    }
}
