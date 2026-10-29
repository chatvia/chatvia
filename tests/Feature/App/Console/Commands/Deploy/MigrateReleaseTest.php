<?php

declare(strict_types=1);

use Domain\Integration\Mcp\Actions\Catalog\SyncBuiltinConnectorsAction;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;

/**
 * chatvia:deploy:migrate is the one list of database steps that Docker's
 * chatvia-migrate and Deployer's deploy.php both run. The steps themselves
 * are tested on their own commands; this checks that a failed check stops
 * the deploy, and that the built-in tools only warn.
 */
it('stops when the embedding settings no longer match the database', function (): void {
    config(['chatvia.embeddings.dimensions' => app(EmbeddingStorage::class)->dimensions() === 768 ? 1024 : 768]);

    $this->artisan('chatvia:deploy:migrate')
        ->doesntExpectOutputToContain('The vector indexes')
        ->doesntExpectOutputToContain('Built-in MCP registry synced.')
        ->assertFailed();
});

it('only warns when the built-in tools cannot be added', function (): void {
    $this->mock(SyncBuiltinConnectorsAction::class)
        ->shouldReceive('execute')
        ->andThrow(new RuntimeException('No embedding API key.'));

    $this->artisan('chatvia:deploy:migrate')
        ->expectsOutputToContain('The built-in tools were not added to the tool catalog: No embedding API key.')
        ->assertSuccessful();
});
