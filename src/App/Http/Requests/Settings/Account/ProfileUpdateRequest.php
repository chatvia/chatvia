<?php

namespace App\Http\Requests\Settings\Account;

use App\Identity\Workspace\Data\Account\UpdateProfileData;
use App\Identity\Workspace\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }

    public function toData(): UpdateProfileData
    {
        /** @var User $user */
        $user = $this->user();

        return new UpdateProfileData(
            user: $user,
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
        );
    }
}
