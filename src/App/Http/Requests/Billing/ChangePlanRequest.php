<?php

namespace App\Http\Requests\Billing;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Data\Subscriptions\SchedulePlanChangeData;
use Domain\Billing\Services\Catalog\BillingCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Schedules a move to another self-serve plan. `package` is the plan key.
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
            'package' => ['required', 'string', Rule::in(app(BillingCatalog::class)->selfServePlans()->keys()->all())],
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
