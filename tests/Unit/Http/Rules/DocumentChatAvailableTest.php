<?php

use App\Http\Rules\DocumentChatAvailable;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Services\DocumentLlm;

/**
 * Chat uploads are PDFs that only an LLM able to read PDF documents can answer
 * from. Without one, the upload is refused with a clear, vendor-free message
 * (BuildWidgetSettingsActionTest checks that the widget then hides its upload
 * button).
 */
function dcaValidate(): ?string
{
    $error = null;

    new DocumentChatAvailable(app(DocumentLlm::class))->validate('file', null, function (string $message) use (&$error): void {
        $error = $message;
    });

    return $error;
}

it('refuses a chat PDF upload when no LLM can read PDF documents', function (): void {
    app()->instance(DocumentLlm::class, new DocumentLlm(null));

    expect(dcaValidate())->toBe('Chatting with PDF files is not available.');
});

it('accepts a chat PDF upload when an LLM can read PDF documents', function (): void {
    app()->instance(DocumentLlm::class, new DocumentLlm(LlmProviderEnum::MISTRAL));

    expect(dcaValidate())->toBeNull();
});
