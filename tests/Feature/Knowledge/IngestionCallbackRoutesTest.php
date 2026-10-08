<?php

/**
 * Ingestion runs entirely on the app's own queue, so there are no
 * server-to-server ingestion callbacks. These paths must stay unrouted: an
 * unauthenticated, CSRF-exempt write path would let a caller change source
 * status or inject chunks into another workspace's knowledge base.
 */
it('does not route any ingestion callback', function (string $method, string $uri): void {
    $this->json($method, $uri, ['job_id' => 'x', 'status' => 'failed', 'agent_id' => 1])
        ->assertNotFound();
})->with([
    'status webhook' => ['PATCH', '/ingestion/webhook'],
    'document callback' => ['POST', '/ingestion/file'],
    'chunk webhook' => ['POST', '/api/chunk'],
]);
