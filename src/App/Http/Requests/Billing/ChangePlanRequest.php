<?php

namespace App\Http\Requests\Billing;

use App\Auth\Models\User;
use Domain\Billing\Data\SchedulePlanChangeData;
use Illuminate\Foundation\Http\FormRequest;

class ChangePlanRequest extends FormRequest
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
            'is_yearly' => ['required', 'boolean'],
        ];
    }

    public function toData(): SchedulePlanChangeData
    {
        /** @var User $user */
        $user = $this->user();

        return new SchedulePlanChangeData(
            user: $user,
            package: $this->string('package')->toString(),
            isYearly: $this->boolean('is_yearly'),
        );
    }
}
