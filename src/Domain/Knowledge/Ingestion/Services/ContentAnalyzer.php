<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services;

use Domain\Knowledge\Ingestion\Data\Analysis\ContentAnalysisData;
use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Data\Chat\ResponseSchemaData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Exceptions\InvalidStructuredReplyException;
use Domain\Llm\Exceptions\NoSummaryModelException;
use Domain\Llm\Services\SourceSummaryModel;
use Domain\Llm\Services\StructuredCompletion;

/**
 * Classifies a whole document (type, language, audience, likely questions),
 * read as a PDF from a URL (a `data:` URL holding the PDF itself, or a link the
 * provider can open) by the model for source summaries
 * ({@see SourceSummaryModel}). The reply must follow a strict JSON schema.
 * The language comes first in it: the model writes the fields in order, and
 * asked for the language last it had already written the questions and
 * topics in a language it guessed, then named that one.
 */
class ContentAnalyzer
{
    public function __construct(
        private readonly SourceSummaryModel $models,
        private readonly StructuredCompletion $completion,
    ) {}

    /**
     * @throws NoSummaryModelException
     * @throws InvalidStructuredReplyException when the reply holds no JSON
     */
    public function analyze(string $documentUrl): ContentAnalysisData
    {
        $reply = $this->completion->send(new ChatRequestData(
            model: $this->models->model(),
            messages: [
                ChatMessageData::userWithAttachment(prompt('summarization/system'), [
                    'type' => 'document_url',
                    'document_url' => $documentUrl,
                ]),
            ],
            tag: AiCallTag::DocumentAnalysis,
            maxTokens: 1024,
            responseSchema: new ResponseSchemaData('document_analysis', $this->fileAnalysisSchema()),
        ));

        return ContentAnalysisData::from($reply->requireData());
    }

    /**
     * @return array<string, mixed>
     */
    private function fileAnalysisSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'language' => [
                    'type' => 'string',
                    'pattern' => '^[a-z]{2}$',
                    'description' => 'Primary language as ISO 639-1 language code (e.g., en, ar, fr, es, de, it, pt, ru, ja, ko, zh, hi). For mixed languages, use the predominant language.',
                ],
                'questions' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'minItems' => 5,
                    'maxItems' => 7,
                    'description' => 'Generate 5-7 distinct questions the document answers, written in the document\'s own language. Each question MUST be a separate array item — do NOT number them, do NOT join them into one string.',
                ],
                'document_type' => [
                    'type' => 'string',
                    'enum' => [
                        'academic_paper',
                        'technical_documentation',
                        'api_documentation',
                        'tutorial',
                        'knowledge_base',
                        'standard_operating_procedure',
                        'user_manual',
                        'business_report',
                        'financial_report',
                        'legal_document',
                        'contract',
                        'policy_document',
                        'email',
                        'memo',
                        'presentation',
                        'research_report',
                        'case_study',
                        'white_paper',
                        'specification',
                        'requirements_document',
                        'meeting_notes',
                        'training_material',
                        'marketing_material',
                        'news_article',
                        'blog_post',
                        'faq',
                        'troubleshooting_guide',
                        'installation_guide',
                        'configuration_guide',
                        'other',
                    ],
                    'description' => 'Most appropriate document type based on purpose, structure, and intended use. Choose the most specific applicable type, not generic classifications.',
                ],
                'metadata' => [
                    'type' => 'object',
                    'properties' => [
                        'complexity_level' => [
                            'type' => 'string',
                            'enum' => ['beginner', 'intermediate', 'advanced', 'expert'],
                            'description' => 'Document complexity based on vocabulary, concepts, and structure',
                        ],
                        'target_audience' => [
                            'type' => 'string',
                            'enum' => [
                                'general_public',
                                'students',
                                'professionals',
                                'technical_experts',
                                'business_users',
                                'developers',
                                'researchers',
                                'administrators',
                                'end_users',
                                'executives',
                                'other',
                            ],
                            'description' => 'Primary intended audience for the document',
                        ],
                        'writing_style' => [
                            'type' => 'string',
                            'enum' => [
                                'academic',
                                'technical',
                                'casual',
                                'formal',
                                'conversational',
                                'instructional',
                                'persuasive',
                                'informative',
                                'analytical',
                                'narrative',
                                'other',
                            ],
                            'description' => 'Primary writing style used in the document',
                        ],
                        'estimated_reading_time_minutes' => [
                            'type' => 'integer',
                            'minimum' => 1,
                            'description' => 'Estimated reading time in minutes for an average reader',
                        ],
                        'key_topics' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                            'maxItems' => 10,
                            'description' => 'Main topics or themes covered in the document (maximum 10)',
                        ],
                        'contains_sensitive_info' => [
                            'type' => 'boolean',
                            'description' => 'Whether the document contains potentially sensitive or confidential information',
                        ],
                    ],
                    'required' => [
                        'complexity_level',
                        'target_audience',
                        'writing_style',
                        'estimated_reading_time_minutes',
                        'key_topics',
                        'contains_sensitive_info',
                    ],
                ],
                'confidence_score' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                    'description' => 'Confidence level in the analysis accuracy (0.0 to 1.0)',
                ],
            ],
            'required' => [
                'language',
                'questions',
                'document_type',
                'metadata',
                'confidence_score',
            ],
            'additionalProperties' => false,
        ];

    }
}
