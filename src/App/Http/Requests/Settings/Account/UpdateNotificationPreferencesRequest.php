<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings\Account;

use Domain\Notification\Data\NotificationPreferencesData;
use Domain\Notification\Enums\NotificationCategory;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The account's notification switches: for every category, bell, desktop
 * alert and sound, each true or false. Everyone may set their own.
 */
class UpdateNotificationPreferencesRequest extends FormRequest
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
        $rules = [];

        foreach (NotificationCategory::cases() as $category) {
            $rules[$category->value] = ['required', 'array'];

            foreach (['bell', 'desktop', 'sound'] as $switch) {
                $rules["{$category->value}.{$switch}"] = ['required', 'boolean'];
            }
        }

        return $rules;
    }

    public function toData(): NotificationPreferencesData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return NotificationPreferencesData::fromStored($validated);
    }
}
