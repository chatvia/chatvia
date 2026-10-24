<?php

use App\Http\Rules\ChatFilesAvailable;
use Domain\Llm\Services\ChatFileModel;

/**
 * A chat file can only be answered by a model that reads it. While chat file
 * uploads are off, or no such model is set up, the upload is refused with a
 * clear, vendor-free message (BuildWidgetSettingsActionTest checks that the
 * widget then hides its upload button).
 */
function cfaValidate(): ?string
{
    $error = null;

    new ChatFilesAvailable(app(ChatFileModel::class))->validate('file', null, function (string $message) use (&$error): void {
        $error = $message;
    });

    return $error;
}

it('refuses a chat file upload while chat file uploads are off', function (): void {
    useChatFiles();
    useChatFiles(false);

    expect(cfaValidate())->toBe('Chatting with files is not available.');
});

it('refuses a chat file upload when no model reads files', function (): void {
    config(['chatvia.ai.chat_files.enabled' => true]);

    expect(cfaValidate())->toBe('Chatting with files is not available.');
});

it('accepts a chat file upload when a model reads files', function (): void {
    useChatFiles();

    expect(cfaValidate())->toBeNull();
});
