<?php

namespace App\Http\Requests\Agent\Source\Web;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a request to train a website again. With `recrawl` its pages are
 * discovered again first; otherwise its current URLs are trained.
 */
class TrainAgentWebsiteRequest extends FormRequest
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
            'recrawl' => ['nullable', 'boolean'],
        ];
    }

    public function recrawl(): bool
    {
        return $this->boolean('recrawl');
    }
}
