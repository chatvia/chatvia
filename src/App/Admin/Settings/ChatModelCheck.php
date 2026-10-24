<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use App\Admin\Settings\Data\ModelCheckData;
use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\ModelsDevCatalog;
use Domain\Llm\Services\ProviderAdapters;
use Domain\Llm\Services\ProviderErrorReason;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Checks that a chat model answers: one short question, sent with the
 * provider, address and key typed (the provider need not be saved yet). A
 * model the list names can still be refused for this key, have no credit
 * left or be gone. The image check shows the model a picture of a cat and
 * asks for a short description, for the model that describes images. The
 * file check sends a one-page PDF file and asks for a short summary, for
 * the model of chat file uploads. The calls are tagged `service-check` in
 * Spectra.
 *
 * A provider limiting requests (429) is busy, not failed: the model may
 * answer in a minute. A failure says what the provider said, and what the
 * provider behind it said when the request went through OpenRouter.
 */
final readonly class ChatModelCheck
{
    private const int MAX_TOKENS = 32;

    private const int REPLY_MAX_TOKENS = 200;

    /** The picture the image check shows: a cartoon cat. */
    private const string IMAGE = 'images/setup/test-cat.png';

    /** The file the PDF check sends: one small page about a bakery. */
    private const string PDF = 'checks/chat-file.pdf';

    private const int MAX_REASON_LENGTH = 300;

    public function __construct(
        private ProviderAdapters $adapters,
        private LlmChat $chat,
        private ModelsDevCatalog $catalog,
        private ProviderErrorReason $reasons,
    ) {}

    public function check(LlmProvider $provider, string $modelId): ModelCheckData
    {
        return $this->ask($provider, $modelId, [ChatMessageData::user('Reply with the word OK.')], self::MAX_TOKENS, showReply: false);
    }

    /** Shows the model a picture of a cat; it passes with its description. */
    public function checkImage(LlmProvider $provider, string $modelId): ModelCheckData
    {
        $image = 'data:image/png;base64,'.base64_encode(File::get(resource_path(self::IMAGE)));

        return $this->ask($provider, $modelId, [
            ChatMessageData::userWithAttachment('Describe this image in one short sentence.', [
                'type' => 'image_url',
                'image_url' => ['url' => $image],
            ]),
        ], self::REPLY_MAX_TOKENS, showReply: true);
    }

    /** Sends the model a one-page PDF file; it passes with its summary. */
    public function checkFile(LlmProvider $provider, string $modelId): ModelCheckData
    {
        $pdf = 'data:application/pdf;base64,'.base64_encode(File::get(resource_path(self::PDF)));

        return $this->ask($provider, $modelId, [
            ChatMessageData::userWithAttachment('Summarize this PDF file in one short sentence.', [
                'type' => 'document_url',
                'document_url' => $pdf,
                'document_name' => File::basename(self::PDF),
            ]),
        ], self::REPLY_MAX_TOKENS, showReply: true);
    }

    /**
     * @param  list<ChatMessageData>  $messages
     */
    private function ask(LlmProvider $provider, string $modelId, array $messages, int $maxTokens, bool $showReply): ModelCheckData
    {
        $model = new Llm(['name' => $modelId, 'capabilities' => $this->catalog->storedEntry($provider->kind, $modelId)]);
        $model->setRelation('provider', $provider);

        try {
            $reply = $this->chat->using($this->adapters->for($provider->kind)->client($provider))->send(new ChatRequestData(
                model: $model,
                messages: $messages,
                tag: AiCallTag::ServiceCheck,
                maxTokens: $maxTokens,
            ))->content;
        } catch (ConnectionException) {
            return ModelCheckData::failed("{$provider->name} could not be reached.");
        } catch (RequestException $exception) {
            return $this->refusal($provider, $exception->response);
        } catch (Throwable $exception) {
            return ModelCheckData::failed($exception->getMessage());
        }

        if (! $showReply) {
            return ModelCheckData::passed();
        }

        $reply = Str::of($reply)->squish()->limit(self::MAX_REASON_LENGTH)->toString();

        return $reply !== '' ? ModelCheckData::passed($reply) : ModelCheckData::failed('The model answered with no text.');
    }

    private function refusal(LlmProvider $provider, Response $response): ModelCheckData
    {
        $status = $response->status();
        $reason = $this->reasons->of($response);

        return match (true) {
            in_array($status, [401, 403], true) => ModelCheckData::failed("{$provider->name} refused this key for this model".($reason !== null ? ": {$reason}" : '.')),
            $status === 429 => ModelCheckData::busy('Busy right now (error 429): the provider is limiting requests. Test again in a minute.'.($reason !== null ? " {$reason}" : '')),
            default => ModelCheckData::failed("The model did not answer (error {$status})".($reason !== null ? ": {$reason}" : '.')),
        };
    }
}
