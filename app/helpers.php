<?php

use App\Support\Agent;
use App\Support\Cohere;
use App\Support\LibreOffice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

if (! function_exists('user_has_capability')) {
    /**
     * Check if the current user has a specific capability
     */
    function user_has_capability(string $capability): bool
    {
        $user = auth()->user();

        return $user ? $user->hasCapability($capability) : false;
    }
}

if (! function_exists('user_capability')) {
    /**
     * Get a capability value for the current user
     */
    function user_capability(string $capability, $default = null)
    {
        $user = auth()->user();

        return $user ? $user->getCapability($capability, $default) : $default;
    }
}

if (! function_exists('user_capability_allows')) {
    /**
     * Check if user's capability allows a specific value
     */
    function user_capability_allows(string $capability, $value = null): bool
    {
        $user = auth()->user();

        return $user ? $user->capabilityAllows($capability, $value) : false;
    }
}

if (! function_exists('plan_has_capability')) {
    /**
     * Check if a specific plan has a capability (static check)
     */
    function plan_has_capability(string $planKey, string $capability): bool
    {
        return \App\Support\Billing::packageHasCapability($planKey, $capability);
    }
}

if (! function_exists('get_plan_features')) {
    /**
     * Get UI features for a plan (for display purposes)
     */
    function get_plan_features(string $planKey, bool $isYearly = false): array
    {
        return \App\Support\Billing::getFeaturesForPackage($planKey, $isYearly);
    }
}

if (! function_exists('get_plan_capabilities')) {
    /**
     * Get capabilities for a plan (for logic checks)
     */
    function get_plan_capabilities(string $planKey): array
    {
        return \App\Support\Billing::getCapabilitiesForPackage($planKey);
    }
}

if (! function_exists('temp_dir')) {
    function temp_dir(): string
    {
        return sys_get_temp_dir();
    }
}

if (! function_exists('create_temp_file')) {
    function create_temp_file(string $filename, string $content): string
    {
        $tempFile = temp_dir().'/'.uniqid('', true).'.'.File::extension($filename);

        file_put_contents($tempFile, $content);

        return $tempFile;
    }
}

if (! function_exists('is_word_file')) {
    function is_word_file(UploadedFile|string $file): bool
    {
        $file = $file instanceof UploadedFile ? $file->path() : $file;
        $mime = mime_content_type($file);

        $wordMimeTypes = [
            'application/msword',                                                                 // .doc
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',          // .docx
        ];

        return in_array($mime, $wordMimeTypes, true);
    }
}

if (! function_exists('is_pdf_file')) {
    function is_pdf_file(UploadedFile|string $file): bool
    {
        $file = $file instanceof UploadedFile ? $file->path() : $file;

        return File::mimeType($file) === 'application/pdf';
    }
}

if (! function_exists('is_text_file')) {
    function is_text_file(UploadedFile|string $file): bool
    {
        $file = $file instanceof UploadedFile ? $file->path() : $file;

        return File::mimeType($file) === 'text/plain';
    }
}

if (! function_exists('office')) {
    function office(): LibreOffice
    {
        return LibreOffice::make();
    }
}

if (! function_exists('embed')) {
    function embed(string $text, bool $returnAsText = false)
    {
        return app(Cohere::class)->embed($text, $returnAsText);
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
