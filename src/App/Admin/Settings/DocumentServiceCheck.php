<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use App\Admin\Settings\Data\ConnectionCheckData;
use Domain\Knowledge\Ingestion\Services\Clients\MistralOcr;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Http\Client\ConnectionException;

/**
 * Checks a service picked in Settings → AI models → Documents with a real
 * call, with the saved key (Settings → AI providers). Mistral OCR reads a
 * small built-in image, so the OCR model and the account are checked as
 * well as the key; Azure answers for its resource; the Mistral models that
 * read whole PDFs answer for the key.
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
        return $reader === AiProvider::Mistral ? $this->mistralOcr() : $this->providers->check($reader);
    }

    /** The models that read whole PDFs, for summaries and PDF chat. */
    public function wholePdf(AiProvider $provider): ConnectionCheckData
    {
        return $this->providers->check($provider);
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

        if (in_array($response->status(), [401, 403], true)) {
            return new ConnectionCheckData(false, keyRefused: true, message: 'Mistral refused the key. Check it in AI providers.');
        }

        $detail = $response->json('message') ?? $response->json('detail') ?? $response->json('error.message');

        return new ConnectionCheckData(false, message: "Mistral OCR answered with an error ({$response->status()})".(is_string($detail) && filled($detail) ? ': '.mb_substr($detail, 0, 200) : '.'));
    }
}
