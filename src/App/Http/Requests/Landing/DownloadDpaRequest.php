<?php

namespace App\Http\Requests\Landing;

use App\Marketing\Data\DpaCustomerData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The self-service "generate your DPA" form. Public: no sign-in needed.
 */
class DownloadDpaRequest extends FormRequest
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
            'company_name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'registered_address' => ['nullable', 'string', 'max:1000'],
            'country' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'signatory_name' => ['nullable', 'string', 'max:255'],
            'signatory_title' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toData(): DpaCustomerData
    {
        return new DpaCustomerData(
            companyName: $this->string('company_name')->toString(),
            registrationNumber: $this->optionalText('registration_number'),
            registeredAddress: $this->optionalText('registered_address'),
            country: $this->optionalText('country'),
            contactEmail: $this->optionalText('contact_email'),
            signatoryName: $this->optionalText('signatory_name'),
            signatoryTitle: $this->optionalText('signatory_title'),
        );
    }

    /** A filled-in field's text, or null when it was left empty. */
    private function optionalText(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->toString() : null;
    }
}
