<?php

use Support\Http\WebhookSignature;

/**
 * Ingestion-pipeline callbacks are authenticated by an HMAC of the raw body
 * (VerifyIngestionSignature). The check runs before validation, so an unsigned
 * caller always gets a 401 and cannot probe which job ids exist.
 */
it('rejects an unsigned status callback before validating it', function (): void {
    // A missing or unknown job id would be a 422 if validation ran first.
    $this->patchJson('/ingestion/webhook', ['job_id' => 'does-not-exist', 'status' => 'failed'])
        ->assertUnauthorized();

    $this->patchJson('/ingestion/webhook', [])
        ->assertUnauthorized();
});

it('rejects a status callback signed with the wrong secret', function (): void {
    $payload = ['job_id' => 'does-not-exist', 'status' => 'failed'];

    $this->withHeaders(['X-Auth-Hash' => WebhookSignature::sign(json_encode($payload), 'wrong-secret')])
        ->patchJson('/ingestion/webhook', $payload)
        ->assertUnauthorized();
});

it('lets a correctly signed callback reach validation', function (): void {
    config(['services.ingestion.secret' => 'ingestion-secret']);

    // An empty body passes the signature check and then fails validation.
    $this->withHeaders(['X-Auth-Hash' => WebhookSignature::sign('[]', 'ingestion-secret')])
        ->patchJson('/ingestion/webhook', [])
        ->assertUnprocessable();
});

it('falls back to the app key when no ingestion secret is set', function (): void {
    config(['services.ingestion.secret' => null]);

    $this->withHeaders(['X-Auth-Hash' => WebhookSignature::sign('[]', (string) config('app.key'))])
        ->patchJson('/ingestion/webhook', [])
        ->assertUnprocessable();
});

it('rejects an unsigned document callback', function (): void {
    $this->postJson('/ingestion/file', ['agent_id' => 1])
        ->assertUnauthorized();
});

it('no longer exposes the no-op chunk webhook', function (): void {
    $this->postJson('/api/chunk', ['chunk_id' => 'x'])->assertNotFound();
});
