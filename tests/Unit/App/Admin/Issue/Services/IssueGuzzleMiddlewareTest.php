<?php

use App\Admin\Issue\Jobs\RecordIssueJob;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Queue;
use Support\Radar\IssueTypeKey;

/**
 * The streamed chat replies use the app's Guzzle client: a refused one
 * becomes an issue, and the code that handles the refusal can still read
 * what the provider said.
 */
it('reports a refused streamed reply and keeps its body readable', function (): void {
    config(['chatvia.issues.enabled' => true]);
    Queue::fake();
    $body = new NoSeekStream(Utils::streamFor('{"error":{"type":"insufficient_quota","message":"You exceeded your current quota"}}'));
    app()->instance('issues-test-handler', new MockHandler([new Response(429, ['Content-Type' => 'application/json'], $body)]));
    $client = app(Client::class);
    $client->getConfig('handler')->setHandler(app('issues-test-handler'));

    try {
        $client->post('https://api.openai.com/v1/responses', ['json' => ['model' => 'gpt-5', 'stream' => true]]);
    } catch (ClientException $e) {
        $said = (string) $e->getResponse()->getBody();
    }

    $issue = Queue::pushed(RecordIssueJob::class)->first()?->issue->issue;

    expect($issue?->type)->toBe(IssueTypeKey::OUT_OF_CREDIT)
        ->and($issue?->model)->toBe('gpt-5')
        ->and($said ?? '')->toContain('insufficient_quota');
});
