<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Source\Youtube;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a YouTube link added as an agent source.
 */
class AddYoutubeVideoRequest extends FormRequest
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
            'url' => ['required', 'url', 'regex:/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.?be)\/.+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.regex' => __('Please enter a valid YouTube URL.'),
        ];
    }

    public function videoUrl(): string
    {
        return (string) $this->validated('url');
    }
}
