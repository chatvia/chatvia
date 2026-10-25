<?php

declare(strict_types=1);

namespace Domain\Llm\Enums;

use Domain\Llm\Services\AiCallTags;

/**
 * What an AI call is for, as the tag Spectra shows on it, so an admin can
 * filter /spectra's requests by purpose (a conversation title, the reply
 * itself, a search embedding…). Each kind of call is wrapped in its tag
 * where it is sent ({@see AiCallTags}). The values are stored in Spectra's
 * tag rows: never rename one.
 */
enum AiCallTag: string
{
    // A chat turn: the reply, and the calls that run before it.
    case ChatReply = 'chat-reply';
    case FollowUpRewrite = 'follow-up-rewrite';
    case ToolRouting = 'tool-routing';
    case KnowledgeSearch = 'knowledge-search';
    case Guardrails = 'guardrails';
    case SearchEmbedding = 'search-embedding';
    case Rerank = 'rerank';

    // After a reply.
    case ConversationTitle = 'conversation-title';
    case ConversationAnalysis = 'conversation-analysis';

    // Agent setup.
    case ConversationStarters = 'conversation-starters';
    case Translation = 'translation';
    case FormDescription = 'form-description';
    case ChatAddress = 'chat-address';

    // Training sources.
    case Embedding = 'embedding';
    case ChunkEnhancement = 'chunk-enhancement';
    case TableDescription = 'table-description';
    case ImageDescription = 'image-description';
    case DocumentSummary = 'document-summary';
    case DocumentAnalysis = 'document-analysis';
    case DocumentReading = 'document-reading';
    case Transcription = 'transcription';

    // Reading a reply aloud.
    case ReadAloud = 'read-aloud';

    // Everything else.
    case AnalyticsAssistant = 'analytics-assistant';
    case Evaluation = 'evaluation';
    case EvaluationJudge = 'evaluation-judge';
    case ServiceCheck = 'service-check';
}
