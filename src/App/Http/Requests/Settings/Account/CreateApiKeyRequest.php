<?php

namespace App\Http\Requests\Settings\Account;

use App\Identity\ApiKey\Data\CreateApiKeyData;
use App\Identity\Workspace\Models\User;
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
