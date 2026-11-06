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

        if (! $schema->hasColumn('spectra_daily_stats', 'stat_key')) {
            $schema->table('spectra_daily_stats', function (Blueprint $table) {
                $table->string('stat_key', 32)->nullable();
                $table->unsignedInteger('latency_samples')->nullable();
            });
        }

        if (! $schema->hasIndex('spectra_daily_stats', 'spectra_daily_stats_stat_key_unique')) {
            $schema->table('spectra_daily_stats', function (Blueprint $table) {
                $table->unique('stat_key', 'spectra_daily_stats_stat_key_unique');
            });
        }

        if (! $schema->hasIndex('spectra_daily_stats', 'spectra_daily_stats_date_trackable')) {
            $schema->table('spectra_daily_stats', function (Blueprint $table) {
                $table->index(['date', 'trackable_type', 'trackable_id'], 'spectra_daily_stats_date_trackable');
            });
        }

        if ($schema->hasIndex('spectra_daily_stats', 'spectra_daily_stats_unique')) {
            $schema->table('spectra_daily_stats', function (Blueprint $table) {
                $table->dropUnique('spectra_daily_stats_unique');
            });
        }

        if ($schema->hasIndex('spectra_daily_stats', 'spectra_daily_stats_model_type_date_index')) {
            $schema->table('spectra_daily_stats', function (Blueprint $table) {
                $table->dropIndex('spectra_daily_stats_model_type_date_index');
            });
        }

        $schema->table('spectra_daily_stats', function (Blueprint $table) {
            $table->string('model', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('spectra_daily_stats', function (Blueprint $table) {
            $table->dropUnique('spectra_daily_stats_stat_key_unique');
            $table->dropIndex('spectra_daily_stats_date_trackable');
            $table->dropColumn(['stat_key', 'latency_samples']);
            $table->index(['date', 'provider', 'model', 'model_type', 'trackable_type', 'trackable_id'], 'spectra_daily_stats_unique');
            $table->index(['model_type', 'date']);
        });
    }
};
