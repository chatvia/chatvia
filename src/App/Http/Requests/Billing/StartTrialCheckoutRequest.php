<?php

namespace App\Http\Requests\Billing;

use Domain\Billing\Data\StartTrialCheckoutData;
use Illuminate\Foundation\Http\FormRequest;

class StartTrialCheckoutRequest extends FormRequest
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
            'package' => ['required', 'string', 'in:starter,standard,pro'],
            'yearly' => ['required', 'boolean'],
        ];
    }

    public function toData(): StartTrialCheckoutData
    {
        return new StartTrialCheckoutData(
            package: $this->string('package')->toString(),
            yearly: $this->boolean('yearly'),
            userIp: (string) $this->ip(),
        );
    }
}
