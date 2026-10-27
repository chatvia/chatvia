<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Capability;

use Closure;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\OfferedMediaModels;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Turning an agent's choice of image or video model for chatters on or off.
 * While the admin does not let agents offer one, it can only be turned off
 * or kept as it is (it then waits, unused).
 */
class UpdateCapabilityModelChoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(OfferedMediaModels $offered): array
    {
        /** @var Agent $agent */
        $agent = $this->route('agent');

        return [
            'enabled' => ['required', 'boolean', function (string $attribute, mixed $value, Closure $fail) use ($agent, $offered): void {
                $kind = $this->kind();

                if ($kind instanceof GeneratedMediaKind && $this->boolean('enabled') && ! $offered->isEnabled($agent, $kind) && ! $offered->isAllowed($kind)) {
                    $fail(__('Your admin does not let agents offer a choice of models.'));
                }
            }],
        ];
    }

    /** The kind the capability in the URL makes; null for any other capability. */
    public function kind(): ?GeneratedMediaKind
    {
        return match ($this->route('slug')) {
            'image-generation' => GeneratedMediaKind::Image,
            'video-generation' => GeneratedMediaKind::Video,
            default => null,
        };
    }
}
