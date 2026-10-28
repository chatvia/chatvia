<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Capability;

use Closure;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\OfferedMediaModels;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Turning an agent's choice of image or video model for chatters on or off.
 * While the admin does not let agents offer one, it can only be turned off
 * or kept as it is (it then waits, unused). Any other capability in the URL
 * has no such choice: a 404.
 *
 * Gated on the route by `agent.permission:agent.sources.train.connectors`
 * and `license.capabilities`.
 */
class UpdateCapabilityModelChoiceRequest extends FormRequest
{
    private ?GeneratedMediaKind $kind = null;

    public function authorize(): bool
    {
        $this->kind();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(OfferedMediaModels $offered): array
    {
        return [
            function (Validator $validator) use ($offered): void {
                /** @var Agent $agent */
                $agent = $this->route('agent');

                if ($this->boolean('enabled') && ! $offered->isEnabled($agent, $this->kind()) && ! $offered->isAllowed($this->kind())) {
                    $validator->errors()->add('enabled', __('Your admin does not let agents offer a choice of models.'));
                }
            },
        ];
    }

    /** The kind the capability in the URL makes. */
    public function kind(): GeneratedMediaKind
    {
        $this->kind ??= GeneratedMediaKind::withModelChoiceFor((string) $this->route('slug'));

        abort_if(! $this->kind instanceof GeneratedMediaKind, 404, __('Capability not found.'));

        return $this->kind;
    }
}
