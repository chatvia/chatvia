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

if (! function_exists('cache_dir')) {
    function cache_dir(): string
    {
        return config('cache.stores.file.path');
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
        return app(LibreOffice::class);
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

if (! function_exists('get_file_type')) {
    function get_file_type(string $mimeType): string
    {
        // Document types (PDF, Word processing, Text, E-books, Web)
        $documentTypes = [
            // PDF
            'application/pdf',

            // Word processing documents
            'application/msword', // doc
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
            'application/vnd.ms-word.document.macroEnabled.12', // docm
            'application/vnd.openxmlformats-officedocument.wordprocessingml.template', // dot
            'application/vnd.ms-word.template.macroEnabled.12', // dotm
            'application/rtf', // rtf
            'application/vnd.oasis.opendocument.text', // odt
            'application/vnd.apple.pages', // pages
            'application/wordperfect', // wpd
            'application/vnd.wordperfect', // wpd
            'application/x-abiword', // abw
            'application/x-hwp', // hwp

            // Text files
            'text/plain', // txt
            'application/xml', // xml
            'text/xml', // xml
            'text/markdown', // md

            // E-books
            'application/epub+zip', // epub

            // Web documents
            'text/html', // htm, html
            'application/xhtml+xml', // html
        ];

        // Presentation types
        $presentationTypes = [
            'application/vnd.ms-powerpoint', // ppt
            'application/vnd.ms-powerpoint.presentation.macroEnabled.12', // pptm
            'application/vnd.openxmlformats-officedocument.presentationml.presentation', // pptx
            'application/vnd.ms-powerpoint.template.macroEnabled.12', // pot
            'application/vnd.openxmlformats-officedocument.presentationml.template', // potx
            'application/vnd.oasis.opendocument.presentation', // odp
            'application/vnd.apple.keynote', // key
        ];

        // Image types
        $imageTypes = [
            'image/jpeg', // jpg, jpeg
            'image/png', // png
            'image/gif', // gif
            'image/bmp', // bmp
            'image/svg+xml', // svg
            'image/tiff', // tiff
            'image/webp', // webp
        ];

        // Spreadsheet types
        $spreadsheetTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
            'application/vnd.ms-excel', // xls
            'application/vnd.ms-excel.sheet.macroEnabled.12', // xlsm
            'application/vnd.ms-excel.sheet.binary.macroEnabled.12', // xlsb
            'text/csv', // csv
            'application/vnd.oasis.opendocument.spreadsheet', // ods
            'application/vnd.apple.numbers', // numbers
            'application/dbase', // dbf
            'application/x-dbf', // dbf
            'text/tab-separated-values', // tsv
            'application/vnd.lotus-1-2-3', // 123
            'application/vnd.ms-works', // wks
            'application/x-quattro-pro', // qpw
        ];

        // Audio types (limited to 20MB)
        $audioTypes = [
            'audio/mpeg', // mp3, mpeg, mpga
            'audio/mp3', // mp3
            'audio/mp4', // mp4, m4a
            'audio/wav', // wav
            'audio/webm', // webm
            'audio/x-wav', // wav
            'audio/wave', // wav
        ];

        // Video types
        $videoTypes = [
            'video/mp4',
            'video/avi',
            'video/mov',
            'video/wmv',
            'video/flv',
            'video/webm',
            'video/mkv',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-ms-wmv',
            'video/3gpp',
            'video/x-flv',
            'video/x-matroska',
        ];

        // Check each category
        if (in_array($mimeType, $documentTypes)) {
            return 'document';
        }

        if (in_array($mimeType, $presentationTypes)) {
            return 'presentation';
        }

        if (in_array($mimeType, $imageTypes)) {
            return 'image';
        }

        if (in_array($mimeType, $spreadsheetTypes)) {
            return 'spreadsheet';
        }

        if (in_array($mimeType, $audioTypes)) {
            return 'audio';
        }

        if (in_array($mimeType, $videoTypes)) {
            return 'video';
        }

        // Fallback: check by mime type prefix
        $mimePrefix = explode('/', $mimeType)[0] ?? '';

        return match ($mimePrefix) {
            'text' => match (true) {
                str_contains($mimeType, 'csv') => 'spreadsheet',
                str_contains($mimeType, 'html') => 'document',
                default => 'document'
            },
            'audio' => 'audio',
            'video' => 'video',
            'image' => 'image',
            'application' => match (true) {
                str_contains($mimeType, 'pdf') => 'document',
                str_contains($mimeType, 'word') => 'document',
                str_contains($mimeType, 'powerpoint') => 'presentation',
                str_contains($mimeType, 'presentation') => 'presentation',
                str_contains($mimeType, 'excel') => 'spreadsheet',
                str_contains($mimeType, 'spreadsheet') => 'spreadsheet',
                str_contains($mimeType, 'document') => 'document',
                str_contains($mimeType, 'epub') => 'document',
                default => 'unsupported'
            },
            default => 'unsupported'
        };
    }
}

if (! function_exists('is_audio_file')) {
    function is_audio_file(string $mimeType): bool
    {
        $audioMimeTypes = [
            'audio/mpeg',           // .mp3
            'audio/mp4',            // .m4a
            'audio/wav',            // .wav
            'audio/wave',           // .wav (alternative)
            'audio/x-wav',          // .wav (alternative)
            'audio/ogg',            // .ogg
            'audio/webm',           // .webm
            'audio/flac',           // .flac
            'audio/aac',            // .aac
            'audio/x-ms-wma',       // .wma
            'audio/amr',            // .amr
            'audio/3gpp',           // .3gp
            'audio/x-m4a',          // .m4a (alternative)
        ];

        return in_array($mimeType, $audioMimeTypes, true);
    }
}

if (! function_exists('current_agent_from_widget')) {
    function current_agent_from_widget(): \App\Models\Agent
    {
        $agentId = request()->header('x-agent_id');

        return \App\Models\Agent::where('external_id', $agentId)->first();
    }
}
