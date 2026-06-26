<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('spectra.storage.connection');
    }

    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());

        $schema->table('spectra_requests', function (Blueprint $table) {
            $table->string('trackable_id')->nullable()->change();
        });

        $schema->table('spectra_daily_stats', function (Blueprint $table) {
            $table->string('trackable_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->getConnection());

        $schema->table('spectra_requests', function (Blueprint $table) {
            $table->uuid('trackable_id')->nullable()->change();
        });

        $schema->table('spectra_daily_stats', function (Blueprint $table) {
            $table->uuid('trackable_id')->nullable()->change();
        });
    }
};
