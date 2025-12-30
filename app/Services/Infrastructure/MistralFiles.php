<?php

namespace App\Services\Infrastructure;

use App\Data\Mistral\MistralFileDeleteResponse;
use App\Data\Mistral\MistralFileResponse;
use App\Data\Mistral\MistralFileSignedUrlResponse;
use App\Services\Infrastructure\LLM\Mistral;

class MistralFiles
{
    public function __construct(public Mistral $mistral) {}

    /**
     * Upload a file to Mistral.
     *
     * @param  string  $filePath  The path to the file to upload
     * @param  string|null  $fileName  Optional custom file name
     * @return MistralFileResponse The file response
     */
    public function upload(string $filePath): MistralFileResponse
    {
        $url = $this->mistral->getBaseUrl().'/files';

        $response = $this->mistral->client()
            ->attach(
                name: 'file',
                contents: file_get_contents($filePath),
            )
            ->post($url, [
                'purpose' => 'ocr',
            ])
            ->throw();

        return MistralFileResponse::from($response->json());
    }

    /**
     * Retrieve a file from Mistral.
     *
     * @param  string  $fileId  The file ID
     * @return MistralFileResponse The file response
     */
    public function retrieve(string $fileId): MistralFileResponse
    {
        $url = $this->mistral->getBaseUrl().'/files/'.$fileId;

        $response = $this->mistral->client()
            ->get($url)
            ->throw();

        return MistralFileResponse::from($response->json());
    }

    /**
     * Delete a file from Mistral.
     *
     * @param  string  $fileId  The file ID
     * @return MistralFileDeleteResponse The delete response
     */
    public function delete(string $fileId): MistralFileDeleteResponse
    {
        $url = $this->mistral->getBaseUrl().'/files/'.$fileId;

        $response = $this->mistral->client()
            ->delete($url)
            ->throw();

        return MistralFileDeleteResponse::from($response->json());
    }

    /**
     * Get a signed URL for a file.
     *
     * @param  string  $fileId  The file ID
     * @return MistralFileSignedUrlResponse The signed URL response
     */
    public function signUrl(string $fileId): MistralFileSignedUrlResponse
    {
        $url = $this->mistral->getBaseUrl().'/files/'.$fileId.'/url';

        $response = $this->mistral->client()
            ->get($url)
            ->throw();

        return MistralFileSignedUrlResponse::from($response->json());
    }
}
