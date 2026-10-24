<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Eval;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Turns "Run evals after changes" on or off for an agent. The route gates it
 * on `agent.permission:agent.evals.manage`, so authorize() defers to it.
 */
class UpdateEvalAutoRunRequest extends FormRequest
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
            'auto_run' => ['required', 'boolean'],
        ];
    }

    public function autoRun(): bool
    {
        return $this->boolean('auto_run');
    }
}
