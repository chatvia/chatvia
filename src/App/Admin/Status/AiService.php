<?php

declare(strict_types=1);

namespace App\Admin\Status;

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Clusters\Settings\Pages\ManageKnowledgeSettings;
use Filament\Support\Icons\Heroicon;

/**
 * An AI (or other outside) service the install calls, with a row on the
 * Status page: what it is for, where it is set up, and whether the install
 * cannot work without it. {@see AiServiceChecks} checks it.
 */
enum AiService: string
{
    case Chat = 'chat';
    case Embeddings = 'embeddings';
    case Reranking = 'reranking';
    case Documents = 'documents';
    case Speech = 'speech';
    case Voice = 'voice';
    case ReadAloud = 'read_aloud';
    case WebPages = 'web_pages';

    public function label(): string
    {
        return match ($this) {
            self::Chat => __('Chat models'),
            self::Embeddings => __('Embeddings'),
            self::Reranking => __('Reranking'),
            self::Documents => __('Document reading'),
            self::Speech => __('Speech to text'),
            self::Voice => __('Voice conversations'),
            self::ReadAloud => __('Read aloud'),
            self::WebPages => __('Web pages'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Chat => __('Write the replies of agents'),
            self::Embeddings => __('Turn knowledge into vectors for search'),
            self::Reranking => __('Put the best search results first'),
            self::Documents => __('Read PDFs, scans and office files'),
            self::Speech => __('Turn recordings and voice notes into text'),
            self::Voice => __('Talk with agents in real time'),
            self::ReadAloud => __('Play agent replies as speech'),
            self::WebPages => __('Firecrawl reads websites for knowledge'),
        };
    }

    public function icon(): Heroicon
    {
        return match ($this) {
            self::Chat => Heroicon::OutlinedChatBubbleLeftRight,
            self::Embeddings => Heroicon::OutlinedCube,
            self::Reranking => Heroicon::OutlinedBarsArrowDown,
            self::Documents => Heroicon::OutlinedDocumentText,
            self::Speech => Heroicon::OutlinedMicrophone,
            self::Voice => Heroicon::OutlinedPhone,
            self::ReadAloud => Heroicon::OutlinedSpeakerWave,
            self::WebPages => Heroicon::OutlinedGlobeAlt,
        };
    }

    /** The card of Settings where it is set up, which the row's "Change" opens. */
    public function settingsUrl(): string
    {
        return match ($this) {
            self::Chat => ManageAiModelSettings::cardUrl('models'),
            self::Embeddings => ManageAiModelSettings::cardUrl('embeddings'),
            self::Reranking => ManageAiModelSettings::cardUrl('reranker'),
            self::Documents => ManageAiModelSettings::cardUrl('documents'),
            self::Speech => ManageAiModelSettings::cardUrl('speech'),
            self::Voice => ManageAiModelSettings::cardUrl('voice'),
            self::ReadAloud => ManageAiModelSettings::cardUrl('read_aloud'),
            self::WebPages => ManageKnowledgeSettings::cardUrl('website_import'),
        };
    }

    /** Agents cannot answer without it, so a missing one is down, not off. */
    public function isRequired(): bool
    {
        return $this === self::Chat || $this === self::Embeddings;
    }
}
