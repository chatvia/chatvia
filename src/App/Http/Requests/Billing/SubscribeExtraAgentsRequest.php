<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\Billing\Concerns\ReturnsToBillingPage;
use App\Identity\Workspace\Models\User;
use Domain\Billing\Data\Subscriptions\SubscribeAddonData;
use Domain\Billing\Enums\Addon;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Buy the extra agents add-on, or change its quantity.
 */
class SubscribeExtraAgentsRequest extends FormRequest
{
    use ReturnsToBillingPage;

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
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function toData(): SubscribeAddonData
    {
        /** @var User $user */
        $user = $this->user();

        return new SubscribeAddonData(
            user: $user,
            addon: Addon::ExtraAgents,
            quantity: $this->integer('quantity'),
            urls: $this->checkoutUrls(),
        );
    }
}
