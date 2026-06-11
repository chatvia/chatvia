<?php

namespace App\Http\Requests\Landing;

use Illuminate\Foundation\Http\FormRequest;

class DownloadDpaRequest extends FormRequest
{
    /** Public self-service landing form — no authentication required. */
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
            'company_name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'registered_address' => ['nullable', 'string', 'max:1000'],
            'country' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'signatory_name' => ['nullable', 'string', 'max:255'],
            'signatory_title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
