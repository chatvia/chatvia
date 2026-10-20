<?php

/**
 * chatvia:search:check-embedding-storage runs after the migrations on every start and
 * deploy: it passes while the settings match the embedding columns, and
 * stops the start when they were changed after install.
 */
it('passes when the embedding columns match the settings', function (): void {
    $this->artisan('chatvia:search:check-embedding-storage')
        ->expectsOutputToContain('Embedding storage matches the settings: halfvec(1024).')
        ->assertSuccessful();
});

it('fails and names the columns when the size changed after install', function (): void {
    config(['chatvia.embeddings.dimensions' => 768]);

    $this->artisan('chatvia:search:check-embedding-storage')
        ->expectsOutputToContain('The settings say halfvec(768), but the database stores embeddings differently:')
        ->expectsOutputToContain('chunks.embedding: halfvec(1024)')
        ->assertFailed();
});

it('fails when the vector type changed after install', function (): void {
    config(['chatvia.embeddings.type' => 'vector']);

    $this->artisan('chatvia:search:check-embedding-storage')
        ->expectsOutputToContain('mcp_tools.embedding: halfvec(1024)')
        ->assertFailed();
});
