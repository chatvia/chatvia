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

        if ($schema->hasTable('spectra_dashboards')) {
            return;
        }

        $schema->create('spectra_dashboards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('trackable_type');
            $table->string('trackable_id')->nullable();
            $table->json('widgets');
            $table->string('default_period', 10)->default('month');
            $table->boolean('show_costs')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['trackable_type', 'trackable_id'], 'spectra_dashboards_trackable_index');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('spectra_dashboards');
    }
};
