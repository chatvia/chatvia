<?php

namespace App\Http\Requests\Agents\Settings;

use Domain\Agents\Data\BusinessProfileData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessProfileRequest extends FormRequest
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
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'opening_hours' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toData(): BusinessProfileData
    {
        return new BusinessProfileData(
            email: $this->input('email'),
            phone: $this->input('phone'),
            website: $this->input('website'),
            address: $this->input('address'),
            opening_hours: $this->input('opening_hours'),
        );
    }
}
