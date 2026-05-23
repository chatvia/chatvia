<?php

namespace App\Services\Chat\Streaming;

use App\Services\Chat\ChatContext;
use Generator;
use JsonException;
use Psr\Http\Message\ResponseInterface;

class OpenAiCompatibleTransformer implements StreamTransformer
{
    public function transform(ResponseInterface $upstream, ChatContext $context): Generator
    {
        $body = $upstream->getBody();
        $buffer = '';
        $generationIdYielded = false;

        while (! $body->eof()) {
            if (connection_status() !== CONNECTION_NORMAL || connection_aborted()) {
                return;
            }

            $chunk = $body->read(1024);
            if ($chunk === '') {
                continue;
            }

            $buffer .= $chunk;

            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newlinePos);
                $buffer = substr($buffer, $newlinePos + 1);

                if (trim($line) === '' || ! str_starts_with($line, 'data: ')) {
                    continue;
                }

                $payload = substr($line, 6);
                if ($payload === '[DONE]') {
                    return;
                }

                try {
                    $json = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    continue;
                }

                if (! is_array($json)) {
                    continue;
                }

                if (! $generationIdYielded && isset($json['id']) && is_string($json['id'])) {
                    $context->generationId = $json['id'];
                    $generationIdYielded = true;
                    yield StreamEvent::meta($context->assistantMessageId, 'generation_id', $json['id']);
                }

                $delta = $json['choices'][0]['delta']['content'] ?? null;
                if (is_string($delta) && $delta !== '') {
                    $context->appendStreamedMessage($delta);
                    yield StreamEvent::delta($context->assistantMessageId, $delta);
                }
            }
        }
    }
}
