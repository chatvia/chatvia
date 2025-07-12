<?php

use App\Support\Agent;
use App\Support\Cohere;
use App\Support\LibreOffice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

if (! function_exists('temp_dir')) {
    function temp_dir(): string
    {
        return sys_get_temp_dir();
    }
}

if (! function_exists('is_word_file')) {
    function is_word_file(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return in_array($extension, ['doc', 'docx']);
    }
}

if (! function_exists('is_pdf_file')) {
    function is_pdf_file(UploadedFile $file): bool
    {
        return File::mimeType($file->path()) === 'application/pdf';
    }
}

if (! function_exists('is_text_file')) {
    function is_text_file(UploadedFile $file): bool
    {
        return File::mimeType($file->path()) === 'text/plain';
    }
}

if (! function_exists('office')) {
    function office(): LibreOffice
    {
        return LibreOffice::make();
    }
}

if (! function_exists('embed')) {
    function embed(string $text)
    {
        return app(Cohere::class)->embed($text);
    }
}

if (! function_exists('template')) {
    function template(string $template, array $placeholders): string
    {
        foreach ($placeholders as $key => $value) {
            $template = str_replace('{'.$key.'}', $value, $template);
        }

        return $template;
    }
}

if (! function_exists('current_agent')) {
    function current_agent(): ?App\Models\Agent
    {
        return Agent::resolveAgentFromSubdomain(request()->subdomain());
    }
}
