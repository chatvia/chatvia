<?php

declare(strict_types=1);

namespace App\Admin\Issue\Services;

use App\Admin\Issue\Types\IssueType;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmFailure;
use Domain\Llm\Services\ProviderErrorReason;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Support\Radar\IssueRaised;

/**
 * Looks at the answer to a call the app made to an outside service: a
 * refusal that an issue type stands for ({@see IssueTypes::forFailure()})
 * is raised, a success tells the service works again, and a connection
 * that never came up is raised too. Calls to hosts that are not the
 * admin's ({@see IssueServices::forUrl()}) are left alone.
 */
final readonly class OutsideCallInspector
{
    public function __construct(
        private IssueServices $services,
        private IssueTypes $types,
        private IssueReporter $reporter,
        private ProviderErrorReason $reason,
    ) {}

    /**
     * @param  array<array-key, mixed>  $requestData  What was sent, to find the model asked for.
     */
    public function answered(string $url, array $requestData, Response $response): void
    {
        $service = $this->services->forUrl($url);

        if ($service === null) {
            return;
        }

        if ($response->successful()) {
            $this->reporter->recovered($service);

            return;
        }

        $type = $this->types->forFailure(LlmFailure::fromResponse($response->status(), $response->body()));
        $model = $this->model($url, $requestData);

        // A per-subject type (a gone model) needs a model asked of an AI
        // provider: a 404 for anything else is an old job or file the
        // service no longer knows.
        if (! $type instanceof IssueType || ($type->perSubject() && ($model === null || ! $this->services->for($service)->provider instanceof AiProvider))) {
            return;
        }

        $this->reporter->report(new IssueRaised(
            service: $service,
            type: $type->key(),
            message: $this->reason->of($response),
            model: $model,
            httpStatus: $response->status(),
            endpoint: $this->endpoint($url),
            subject: $type->perSubject() ? $model : null,
        ));
    }

    public function unreachable(string $url, string $message): void
    {
        $service = $this->services->forUrl($url);
        $type = $this->types->forFailure(LlmFailure::Unreachable);

        if ($service !== null && $type instanceof IssueType) {
            $this->reporter->report(new IssueRaised(
                service: $service,
                type: $type->key(),
                message: Str::of($message)->replaceMatches('/\?\S*/', '')->limit(300)->toString(),
                endpoint: $this->endpoint($url),
            ));
        }
    }

    /**
     * The model a call asked for: the `model` it sent, or the one in a
     * Gemini address (`…/models/gemini-x:generateContent`).
     *
     * @param  array<array-key, mixed>  $requestData
     */
    private function model(string $url, array $requestData): ?string
    {
        $sent = $requestData['model'] ?? null;

        if (filled($sent) && is_scalar($sent)) {
            return Str::limit((string) $sent, 200, '');
        }

        $inPath = Str::match('#/models/([^/:]+)#', Uri::of($url)->path());

        return filled($inPath) ? $inPath : null;
    }

    /** The host and path called, never the query string (it may hold a key). */
    private function endpoint(string $url): string
    {
        $uri = Uri::of($url);

        return Str::of($uri->host() ?? '')->append('/', $uri->path())->rtrim('/')->limit(250, '')->toString();
    }
}
