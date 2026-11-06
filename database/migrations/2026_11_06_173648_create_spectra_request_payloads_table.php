<?php

declare(strict_types=1);

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

        if ($schema->hasTable('spectra_request_payloads')) {
            return;
        }

        $schema->create('spectra_request_payloads', function (Blueprint $table) {
            $table->uuid('request_id')->primary();
            $table->longText('request')->nullable();
            $table->longText('response')->nullable();
            $table->dateTime('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('spectra_request_payloads');
    }
};
