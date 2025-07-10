<?php

namespace App\Http\Requests\Agents;

use App\Rules\SubdomainRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $allowedMimeTypes = [
            'application/pdf',
            'application/x-pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
            'text/markdown',
            'text/plain',
        ];

        $rules = [
            'name' => ['required', 'string'],
            'sub_domain' => ['required', new SubdomainRule],
            'system_instructions' => ['required', 'string'],
            'is_public' => ['required', 'boolean'],

            'type' => ['required', 'string', 'in:knowledge,instruction'],
        ];

        if ($this->input('type') === 'knowledge') {
            $rules['files'] = [
                'required',
                'array',
                'min:1',
                'max:10', // Limits the array to a maximum of 10 items
            ];
            $rules['files.*'] = [
                'file',
                'mimetypes:'.implode(',', $allowedMimeTypes),
                'max:10240', // Limits each file to 10MB (10240 KB)
            ];
        }

        return $rules;
    }
}
