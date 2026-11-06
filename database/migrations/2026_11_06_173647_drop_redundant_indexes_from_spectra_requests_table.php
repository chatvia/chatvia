<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected const array REDUNDANT = [
        'spectra_requests' => [

            'spectra_requests_batch_id_index',

            'spectra_requests_trace_id_index',
            'spectra_requests_trackable_type_trackable_id_index',

            'spectra_requests_status_code_created_at_index',
        ],

        'spectra_daily_stats' => ['spectra_daily_stats_trackable_type_trackable_id_index'],
    ];

    public function getConnection(): ?string
    {
        return config('spectra.storage.connection');
    }

    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());

        foreach (self::REDUNDANT as $tableName => $indexes) {
            foreach ($indexes as $index) {
                if (! $schema->hasIndex($tableName, $index)) {
                    continue;
                }

                $schema->table($tableName, function (Blueprint $table) use ($index) {
                    $table->dropIndex($index);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('spectra_requests', function (Blueprint $table) {
            $table->index('batch_id');
            $table->index('trace_id');
            $table->index(['trackable_type', 'trackable_id']);
            $table->index(['status_code', 'created_at']);
        });

        Schema::connection($this->getConnection())->table('spectra_daily_stats', function (Blueprint $table) {
            $table->index(['trackable_type', 'trackable_id']);
        });
    }
};
