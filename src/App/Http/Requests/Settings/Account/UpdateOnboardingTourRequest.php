<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings\Account;

use App\Identity\Workspace\Enums\OnboardingTourStage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * How far the signed-in person got in the dashboard tour; a null stage
 * starts it again.
 */
class UpdateOnboardingTourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'stage' => ['present', 'nullable', Rule::enum(OnboardingTourStage::class)],
        ];
    }
}
