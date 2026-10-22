<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Utils;
use Psr\Http\Message\RequestInterface;

/**
 * One HTTP handler for the AI calls of a request or job that are sent at the
 * same time ({@see LlmChat::sendAsync()}). Async requests only move forward
 * together when they share a handler: waiting on one then also moves the
 * others, as with `Http::pool`. Bound scoped, so each request or job has its
 * own.
 */
final class SharedHttpHandler
{
    /** @var (callable(RequestInterface, array<string, mixed>): PromiseInterface)|null */
    private $handler;

    /**
     * @return callable(RequestInterface, array<string, mixed>): PromiseInterface
     */
    public function handler(): callable
    {
        return $this->handler ??= Utils::chooseHandler();
    }
}
