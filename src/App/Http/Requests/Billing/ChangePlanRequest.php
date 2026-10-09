<?php

namespace App\Http\Requests\Billing;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Data\Subscriptions\SchedulePlanChangeData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Schedules a move to another self-serve plan.
 */
class ChangePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
