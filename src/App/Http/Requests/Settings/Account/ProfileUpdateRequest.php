<?php

namespace App\Http\Requests\Settings\Account;

use App\Identity\Workspace\Data\Account\UpdateProfileData;
use App\Identity\Workspace\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The profile form: name, email and an optional new photo.
 */
class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Staff emails are stored lowercase. The typed address is lowered
     * before the rules run, so the unique check also sees an address that
     * differs only in case.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower($this->input('email'))]);
        }
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
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
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
            photo: $this->file('photo'),
        );
    }
}
