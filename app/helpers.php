<?php

use App\Services\Agents\Agent;
use App\Services\HostConfig;
use App\Services\Infrastructure\Documents\LibreOffice;
use App\Services\Infrastructure\Embeddings\Cohere;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

if (! function_exists('temp_dir')) {
    function temp_dir(string $append = ''): string
    {
        $path = sys_get_temp_dir().$append;

        // If a subdirectory path is appended and doesn't exist, create it
        if ($append !== '' && ! is_dir($path)) {
            if (! mkdir($path, 0755, true) && ! is_dir($path)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $path));
            }
        }

        return $path;
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

if (! function_exists('current_team')) {
    function current_team(): ?App\Models\Team
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        // Check session first
        $currentTeamId = session('current_team_id');

        if ($currentTeamId) {
            $team = \App\Models\Team::find($currentTeamId);

            if ($team && ($user->ownsTeam($team) || $user->belongsToTeam($team))) {
                return $team;
            }
        }

        // Fall back to primary team
        $primaryTeam = $user->primaryTeam();

        if ($primaryTeam) {
            session(['current_team_id' => $primaryTeam->id]);
        }

        return $primaryTeam;
    }
}

if (! function_exists('get_file_type')) {
    function get_file_type(string $mimeType): string
    {
        $wordFiles = config('mimes.word');
        $pdfFiles = config('mimes.pdf');
        $textFiles = config('mimes.text');

        // Presentation types
        $presentationTypes = config('mimes.presentation');

        // Image types
        $imageTypes = config('mimes.images');

        // Spreadsheet types
        $spreadsheetTypes = config('mimes.spreadsheets');

        // Audio types (limited to 20MB)
        $audioTypes = config('mimes.audio');

        // Video types
        $videoTypes = config('mimes.video');

        // Check each category
        if (in_array($mimeType, $pdfFiles, true)) {
            return 'pdf';
        }

        if (in_array($mimeType, $wordFiles, true)) {
            return 'word';
        }

        if (in_array($mimeType, $textFiles, true)) {
            return 'text';
        }

        if (in_array($mimeType, $presentationTypes, true)) {
            return 'presentation';
        }

        if (in_array($mimeType, $imageTypes, true)) {
            return 'image';
        }

        if (in_array($mimeType, $spreadsheetTypes, true)) {
            return 'spreadsheet';
        }

        if (in_array($mimeType, $audioTypes, true)) {
            return 'audio';
        }

        if (in_array($mimeType, $videoTypes, true)) {
            return 'video';
        }

        throw new RuntimeException('Unsupported mime type');
    }
}

if (! function_exists('is_audio_file')) {
    function is_audio_file(UploadedFile|string $file): bool
    {
        $filePath = $file instanceof UploadedFile ? $file->path() : $file;

        $mimeType = File::mimeType($filePath);

        return in_array($mimeType, config('mimes.audio'), true);
    }
}

if (! function_exists('is_video_file')) {
    function is_video_file(UploadedFile|string $file): bool
    {
        $filePath = $file instanceof UploadedFile ? $file->path() : $file;

        $mimeType = File::mimeType($filePath);

        return in_array($mimeType, config('mimes.video'), true);
    }
}

if (! function_exists('current_agent_from_widget')) {
    function current_agent_from_widget(): \App\Models\Agent
    {
        $agentId = request()->header('x-agent_id');

        return \App\Models\Agent::where('external_id', $agentId)->first();
    }
}

if (! function_exists('landing')) {
    function landing(?string $key = null): string|array
    {
        $landing = HostConfig::landing(request()->host());

        if ($key) {
            return $landing[$key];
        }

        return $landing;
    }
}

if (! function_exists('host_config')) {
    function host_config(?string $key = null): mixed
    {
        $config = HostConfig::get(request()->host());

        if ($key) {
            return $config[$key];
        }

        return $config;
    }
}
