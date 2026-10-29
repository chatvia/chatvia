<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use App\Admin\Issue\Services\IssueRecording;
use App\Admin\Settings\Data\ConnectionCheckData;
use Domain\Knowledge\Ingestion\Services\Clients\MistralOcr;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;

/**
 * Checks a service picked in Settings → AI models → Documents with a real
 * call, with the saved key (Settings → AI providers). Mistral OCR reads a
 * small built-in image, so the OCR model and the account are checked as
 * well as the key; Azure answers for its resource.
 */
final readonly class DocumentServiceCheck
{
    public function __construct(
        private AiProviderCheck $providers,
        private MistralOcr $ocr,
    ) {}

    /** The service that reads documents while sources are trained. */
    public function reader(AiProvider $reader): ConnectionCheckData
    {
        // A check never becomes an issue: it may test a key the admin is still typing.
        return IssueRecording::muted(fn (): ConnectionCheckData => $this->readerUnmuted($reader));
    }

    private function readerUnmuted(AiProvider $reader): ConnectionCheckData
    {
        return $reader === AiProvider::Mistral ? $this->mistralOcr() : $this->providers->check($reader);
    }

    private function mistralOcr(): ConnectionCheckData
    {
        try {
            $response = $this->ocr->check();
        } catch (ConnectionException) {
            return new ConnectionCheckData(false, message: 'Mistral could not be reached. Try again in a moment.');
        }

        if ($response->successful()) {
            return new ConnectionCheckData(true);
        }

        if (collect([401, 403])->containsStrict($response->status())) {
            return new ConnectionCheckData(false, keyRefused: true, message: 'Mistral refused the key. Check it in AI providers.');
        }

        $detail = $response->json('message') ?? $response->json('detail') ?? $response->json('error.message');

        return new ConnectionCheckData(false, message: "Mistral OCR answered with an error ({$response->status()})".(filled($detail) ? ': '.Str::substr($detail, 0, 200) : '.'));
    }
}
