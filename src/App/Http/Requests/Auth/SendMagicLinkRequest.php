<?php

namespace App\Http\Requests\Auth;

use App\Auth\Data\Sessions\SendMagicLinkData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendMagicLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
        ];
    }

    public function toData(): SendMagicLinkData
    {
        return new SendMagicLinkData(
            email: $this->string('email')->toString(),
        );
    }
}
