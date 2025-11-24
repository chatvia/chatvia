<?php

namespace App\Services\Infrastructure;

use Aws\Comprehend\ComprehendClient;
use Aws\Exception\AwsException;
use RuntimeException;

class AmazonComprehend
{
    private ComprehendClient $client;

    public function __construct()
    {
        $this->client = new ComprehendClient([
            'version' => 'latest',
            'region' => config('services.comprehend.region', 'us-east-1'),
            'credentials' => [
                'key' => config('services.comprehend.key'),
                'secret' => config('services.comprehend.secret'),
            ],
        ]);
    }

    /**
     * Analyze the sentiment of the provided text.
     *
     * @param  string  $text  The text to analyze (max 5000 bytes UTF-8 encoded)
     * @param  string  $languageCode  The language code (e.g., 'en', 'es', 'fr')
     * @return array{Sentiment: string, SentimentScore: array{Positive: float, Negative: float, Neutral: float, Mixed: float}}
     *
     * @throws RuntimeException
     */
    public function detectSentiment(string $text, string $languageCode = 'en'): array
    {
        try {
            $result = $this->client->detectSentiment([
                'Text' => $text,
                'LanguageCode' => $languageCode,
            ]);

            return [
                'Sentiment' => $result['Sentiment'],
                'SentimentScore' => [
                    'Positive' => $result['SentimentScore']['Positive'],
                    'Negative' => $result['SentimentScore']['Negative'],
                    'Neutral' => $result['SentimentScore']['Neutral'],
                    'Mixed' => $result['SentimentScore']['Mixed'],
                ],
            ];
        } catch (AwsException $e) {
            throw new RuntimeException(
                sprintf(
                    'AWS Comprehend API error: %s (Code: %s, Type: %s)',
                    $e->getAwsErrorMessage() ?? $e->getMessage(),
                    $e->getAwsErrorCode() ?? 'Unknown',
                    $e->getAwsErrorType() ?? 'Unknown'
                ),
                $e->getStatusCode() ?? 0,
                $e
            );
        } catch (\Exception $e) {
            throw new RuntimeException(
                'AWS Comprehend error: '.$e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Analyze the sentiment of multiple text items in a single request.
     *
     * @param  array<string>  $textList  Array of text items to analyze (max 25 items)
     * @param  string  $languageCode  The language code (e.g., 'en', 'es', 'fr')
     * @return array<array{Index: int, Sentiment: string, SentimentScore: array{Positive: float, Negative: float, Neutral: float, Mixed: float}}>
     *
     * @throws RuntimeException
     */
    public function batchDetectSentiment(array $textList, string $languageCode = 'en'): array
    {
        try {
            $result = $this->client->batchDetectSentiment([
                'TextList' => $textList,
                'LanguageCode' => $languageCode,
            ]);

            return array_map(function ($item) {
                return [
                    'Index' => $item['Index'],
                    'Sentiment' => $item['Sentiment'],
                    'SentimentScore' => [
                        'Positive' => $item['SentimentScore']['Positive'],
                        'Negative' => $item['SentimentScore']['Negative'],
                        'Neutral' => $item['SentimentScore']['Neutral'],
                        'Mixed' => $item['SentimentScore']['Mixed'],
                    ],
                ];
            }, $result['ResultList']);
        } catch (AwsException $e) {
            throw new RuntimeException(
                sprintf(
                    'AWS Comprehend Batch API error: %s (Code: %s, Type: %s)',
                    $e->getAwsErrorMessage() ?? $e->getMessage(),
                    $e->getAwsErrorCode() ?? 'Unknown',
                    $e->getAwsErrorType() ?? 'Unknown'
                ),
                $e->getStatusCode() ?? 0,
                $e
            );
        } catch (\Exception $e) {
            throw new RuntimeException(
                'AWS Comprehend Batch error: '.$e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
