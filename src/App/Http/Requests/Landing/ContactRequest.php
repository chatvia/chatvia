<?php

namespace App\Http\Requests\Landing;

use App\Marketing\Data\ContactMessageData;
use Illuminate\Foundation\Http\FormRequest;
use RyanChandler\LaravelCloudflareTurnstile\Rules\Turnstile;

/**
 * The public contact form. Guests may send it; a Turnstile challenge keeps
 * bots out.
 */
class ContactRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'cf-turnstile-response' => ['required', new Turnstile],
        ];
    }

    public function toData(): ContactMessageData
    {
        return new ContactMessageData(
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            company: $this->filled('company') ? $this->string('company')->toString() : null,
            message: $this->string('message')->toString(),
        );
    }
}
