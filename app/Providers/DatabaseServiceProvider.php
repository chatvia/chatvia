<?php

namespace App\Providers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        PostgresGrammar::macro('typeBm25vector', function () {
            return 'bm25_catalog.bm25vector';
        });

        Blueprint::macro('bm25vector', function (string $column) {
            return $this->addColumn('bm25vector', $column);
        });
    }
}
