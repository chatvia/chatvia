<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\Billing\Concerns\ReturnsToBillingPage;
use App\Identity\Workspace\Models\User;
use Domain\Billing\Data\Subscriptions\SubscribeAddonData;
use Domain\Billing\Enums\Addon;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Buy the Remove Branding add-on (a yes/no add-on, always one).
 */
class SubscribeRemoveBrandingRequest extends FormRequest
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
        return [];
    }

    public function toData(): SubscribeAddonData
    {
        /** @var User $user */
        $user = $this->user();

        return new SubscribeAddonData(
            user: $user,
            addon: Addon::RemoveBranding,
            quantity: 1,
            urls: $this->checkoutUrls(),
        );
    }
}
