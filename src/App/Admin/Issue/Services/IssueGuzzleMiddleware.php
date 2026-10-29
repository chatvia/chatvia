<?php

declare(strict_types=1);

namespace App\Admin\Issue\Services;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Lets {@see OutsideCallInspector} see the calls made with the app's own
 * Guzzle client (the streamed chat replies), which Laravel's HTTP client
 * events never see. A refused call's body is read here to tell what went
 * wrong, so a streamed one is put back as a readable copy for the code
 * that handles the refusal.
 */
final readonly class IssueGuzzleMiddleware
{
    public function __construct(private OutsideCallInspector $inspector) {}

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $url = (string) $request->getUri();

            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($request, $url): ResponseInterface {
                    if ($response->getStatusCode() < 400) {
                        rescue(fn () => $this->inspector->answered($url, [], new Response($response)), report: false);

                        return $response;
                    }

                    $readable = $this->readable($response);
                    rescue(fn () => $this->inspector->answered($url, $this->sent($request), new Response($readable)), report: false);

                    return $readable;
                },
                function (Throwable $reason) use ($request, $url): PromiseInterface {
                    if ($reason instanceof RequestException && $reason->getResponse() instanceof ResponseInterface) {
                        $readable = $this->readable($reason->getResponse());
                        rescue(fn () => $this->inspector->answered($url, $this->sent($request), new Response($readable)), report: false);
                        $reason = RequestException::create($request, $readable, $reason->getPrevious(), $reason->getHandlerContext());
                    } elseif ($reason instanceof ConnectException) {
                        rescue(fn () => $this->inspector->unreachable($url, $reason->getMessage()), report: false);
                    }

                    return Create::rejectionFor($reason);
                },
            );
        };
    }

    /** The response with a body that can be read more than once. */
    private function readable(ResponseInterface $response): ResponseInterface
    {
        $body = $response->getBody();

        if ($body->isSeekable()) {
            return $response;
        }

        return $response->withBody(Utils::streamFor($body->getContents()));
    }

    /**
     * What a call sent, when it was JSON, to find the model it asked for.
     *
     * @return array<array-key, mixed>
     */
    private function sent(RequestInterface $request): array
    {
        $body = $request->getBody();

        if (! $body->isSeekable() || ! Str::contains($request->getHeaderLine('Content-Type'), 'json')) {
            return [];
        }

        $json = (string) $body;
        $body->rewind();

        return Str::isJson($json) ? (array) json_decode($json, true, flags: JSON_THROW_ON_ERROR) : [];
    }
}
