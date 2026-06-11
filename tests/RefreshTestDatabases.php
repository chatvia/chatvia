<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Tests\Support\ProvisionTestDatabase;

trait RefreshTestDatabases
{
    use DatabaseTransactions {
        beginDatabaseTransaction as protected startDatabaseTransaction;
    }

    /**
     * Laravel's setUpTraits() detects the nested DatabaseTransactions trait
     * and calls beginDatabaseTransaction() before every test — overriding it
     * is what hooks the migrate-if-necessary step in BEFORE the transaction
     * opens. Migrations must never run inside the test transaction: Postgres
     * DDL is transactional, so the schema would roll back with the test.
     */
    public function beginDatabaseTransaction(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            (new ProvisionTestDatabase)();

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->startDatabaseTransaction();
    }
}
