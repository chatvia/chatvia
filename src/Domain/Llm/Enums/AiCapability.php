<?php

declare(strict_types=1);

namespace Domain\Llm\Enums;

/**
 * Something an AI provider can do for this install: answer in chat, turn
 * text into search vectors, turn speech into text, read documents, or rank
 * search results. {@see AiProvider::capabilities()} says which provider does
 * what.
 */
enum AiCapability: string
{
    case Chat = 'chat';
    case Embeddings = 'embeddings';
    case Speech = 'speech';
    case Documents = 'documents';
    case Reranking = 'reranking';

    /** The short label of a chip on the AI providers page. */
    public function label(): string
    {
        return match ($this) {
            self::Chat => 'chat',
            self::Embeddings => 'embeddings',
            self::Speech => 'speech to text',
            self::Documents => 'documents',
            self::Reranking => 'reranking',
        };
    }
}
