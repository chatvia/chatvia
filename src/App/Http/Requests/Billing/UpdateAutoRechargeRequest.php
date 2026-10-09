<?php

namespace App\Http\Requests\Billing;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Data\Account\UpdateAutoRechargeData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the auto-recharge settings form: whether it is on, the message
 * balance that triggers a top-up, and how many messages each top-up buys. The
 * bounds keep a misconfigured form from charging tiny or very large amounts.
 */
class UpdateAutoRechargeRequest extends FormRequest
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
            'enabled' => ['required', 'boolean'],
            'threshold' => ['required', 'integer', 'min:100', 'max:100000'],
            'amount' => ['required', 'integer', 'min:1000', 'max:100000'],
        ];
    }

    public function toData(): UpdateAutoRechargeData
    {
        /** @var User $user */
        $user = $this->user();

        return new UpdateAutoRechargeData(
            user: $user,
            enabled: $this->boolean('enabled'),
            threshold: $this->integer('threshold'),
            amount: $this->integer('amount'),
        );
    }
}
