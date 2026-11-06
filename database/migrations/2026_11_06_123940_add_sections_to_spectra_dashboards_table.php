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

        if ($schema->hasColumn('spectra_dashboards', 'sections')) {
            return;
        }

        $schema->table('spectra_dashboards', function (Blueprint $table) {
            $table->json('sections')->nullable();
            $table->boolean('show_messages')->default(false);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('spectra_dashboards', function (Blueprint $table) {
            $table->dropColumn(['sections', 'show_messages']);
        });
    }
};
