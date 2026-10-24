<?php

declare(strict_types=1);

namespace Domain\Llm\Enums;

use Domain\Llm\Exceptions\NoLlmAvailableException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Why an AI call failed, in a few kinds an admin can act on: the provider no
 * longer serves the model, refused the key, the account ran out of credit,
 * and so on. Stored in guardrail event details and shown in notifications,
 * so the values must not change.
 */
enum LlmFailure: string
{
    case ModelUnavailable = 'model_unavailable';
    case KeyRefused = 'key_refused';
    case OutOfCredit = 'out_of_credit';
    case RateLimited = 'rate_limited';
    case Unreachable = 'unreachable';
    case ProviderError = 'provider_error';
    case NoModel = 'no_model';
    case BadReply = 'bad_reply';
    case Other = 'other';

    /**
     * The kind of failure an exception from an AI call stands for. Streamed
     * calls throw Guzzle's exceptions, the others Laravel's HTTP client ones.
     */
    public static function of(Throwable $e): self
    {
        if ($e instanceof NoLlmAvailableException) {
            return self::NoModel;
        }

        if ($e instanceof ConnectionException || $e instanceof ConnectException) {
            return self::Unreachable;
        }

        if ($e instanceof RequestException) {
            return self::fromResponse($e->response->status(), $e->response->body());
        }

        if ($e instanceof GuzzleRequestException && $e->getResponse() !== null) {
            return self::fromResponse($e->getResponse()->getStatusCode(), (string) $e->getResponse()->getBody());
        }

        return self::Other;
    }

    private static function fromResponse(int $status, string $body): self
    {
        // Anthropic answers an empty balance with a 400, OpenAI with a 429.
        if ($status === 402 || Str::contains($body, ['insufficient_quota', 'credit balance'], ignoreCase: true)) {
            return self::OutOfCredit;
        }

        return match (true) {
            $status === 404 => self::ModelUnavailable,
            $status === 401, $status === 403 => self::KeyRefused,
            $status === 429 => self::RateLimited,
            $status >= 500 => self::ProviderError,
            default => self::Other,
        };
    }
}
