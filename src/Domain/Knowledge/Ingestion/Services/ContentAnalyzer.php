<?php

namespace Domain\Knowledge\Ingestion\Services;

use Domain\Analytics\Data\ContentAnalysisData;
use Domain\Llm\Exceptions\DocumentLlmUnavailableException;
use Domain\Llm\Services\DocumentLlm;
use Exception;
use Illuminate\Http\Client\Response;

/**
 * Classifies a whole document (type, language, audience, likely questions),
 * read as a PDF from a signed URL by the configured document-reading LLM
 * (`chatvia.ai.models.analysis`).
 */
class ContentAnalyzer
{
    public function __construct(
        private readonly DocumentLlm $documentLlm,
    ) {}

    public function fromUrl(string $documentUrl): ContentAnalysisData
    {
        return $this->parse($this->complete($documentUrl));
    }

    /**
     * The raw completion response, for callers that also need its `usage`.
     *
     * @throws DocumentLlmUnavailableException
     */
    public function complete(string $documentUrl): Response
    {
        return $this->documentLlm->provider()->completion([
            'model' => (string) config('chatvia.ai.models.analysis'),
            'max_tokens' => 1024,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => prompt('summarization/system'),
                        ],
                        [
                            'type' => 'document_url',
                            'document_url' => $documentUrl,
                        ],
                    ],
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'document_analysis',
                    'strict' => true,
                    'schema' => $this->fileAnalysisSchema(),
                ],
            ],
        ]);
    }

    public function parse(Response $completion): ContentAnalysisData
    {
        $response = $completion->json();

        if (! isset($response['choices'][0]['message']['content'])) {
            throw new Exception('No valid response content found');
        }

        $content = $response['choices'][0]['message']['content'];

        $data = is_string($content)
            ? json_decode($content, true, 512, JSON_THROW_ON_ERROR)
            : $content;

        return ContentAnalysisData::from($data);
    }

    private function fileAnalysisSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'questions' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'minItems' => 5,
                    'maxItems' => 7,
                    'description' => 'Generate 5-7 distinct questions the document answers. Each question MUST be a separate array item — do NOT number them, do NOT join them into one string.',
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
                'language' => [
                    'type' => 'string',
                    'pattern' => '^[a-z]{2}$',
                    'description' => 'Primary language as ISO 639-1 language code (e.g., en, ar, fr, es, de, it, pt, ru, ja, ko, zh, hi). For mixed languages, use the predominant language.',
                ],
                'confidence_score' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                    'description' => 'Confidence level in the analysis accuracy (0.0 to 1.0)',
                ],
            ],
            'required' => [
                'questions',
                'document_type',
                'metadata',
                'language',
                'confidence_score',
            ],
            'additionalProperties' => false,
        ];

    }
}
