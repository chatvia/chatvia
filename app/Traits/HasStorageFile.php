<?php

namespace App\Traits;

use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;

trait HasStorageFile
{
    public function readFile($field = 'filepath'): File
    {
        if (! $this->filepath) {
            throw new Exception("File path is not set for AgentFile ID: {$this->id}");
        }

        $data = Storage::get($this->$field);

        if ($data === null) {
            throw new Exception("File not found in storage: {$this->$field}");
        }

        $extension = pathinfo($this->$field, PATHINFO_EXTENSION);

        $tempPath = tempnam(sys_get_temp_dir(), 'agent_file_').'.'.$extension;

        file_put_contents($tempPath, $data);

        return new File($tempPath);
    }

    public function signedUrl(): string
    {
        return Storage::temporaryUrl($this->filepath, now()->addDay());
    }

    public function toPdf(bool $signed = false): string
    {
        $filePath = $this->filepath;
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        $field = $this->parser_filepath ? 'parser_filepath' : 'filepath';

        if ($extension !== 'pdf') {
            $pdfFilePath = office()->toPdf($this->readFile($field)->getPathname());

            $filePath = 'users/'.$this->user_id.'/tmp/'.Str::uuid()->toString().'.pdf';

            Storage::put($filePath, file_get_contents($pdfFilePath));
        }

        if ($signed) {
            return Storage::temporaryUrl($filePath, now()->addDay());
        }

        return $filePath;
    }
}
