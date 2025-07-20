<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class VectorChordServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/vector.php', 'vector'
        );
    }

    public function boot(): void
    {
        // Configure PostgreSQL for VectorChord optimization
        $this->configurePostgreSQLForVectorChord();

        // Set up runtime VectorChord parameters
        $this->setupRuntimeParameters();

    }

    private function configurePostgreSQLForVectorChord(): void
    {
        $memoryConfig = config('vector.performance.memory', []);

        // Set work_mem for vector operations (session-level)
        $workMem = $memoryConfig['work_mem'] ?? '256MB';
        DB::statement("SET work_mem = '{$workMem}'");

        // Set maintenance_work_mem for index building (session-level)
        $maintenanceWorkMem = $memoryConfig['maintenance_work_mem'] ?? '1GB';
        DB::statement("SET maintenance_work_mem = '{$maintenanceWorkMem}'");

        // Enable parallel workers for index operations (session-level)
        $maxParallelWorkers = config('vector.indexing.build_threads', 4);
        DB::statement("SET max_parallel_maintenance_workers = {$maxParallelWorkers}");
        DB::statement("SET max_parallel_workers = {$maxParallelWorkers}");

        // Optimize for SSD storage (session-level)
        DB::statement('SET random_page_cost = 1.1');
        DB::statement('SET seq_page_cost = 1.0');

        // Set search path for VectorChord extensions
        //        DB::statement('SET search_path = public, bm25_catalog, tokenizer_catalog');
    }

    /**
     * Set up runtime VectorChord parameters
     */
    private function setupRuntimeParameters(): void
    {
        // Set default VectorChord search parameters
        $defaultProbes = config('vector.search.probes.default', 20);
        DB::statement("SET vchordrq.probes = {$defaultProbes}");

        // Configure MaxSim if enabled
        if (config('vector.search.maxsim.enabled', false)) {
            $maxsimRefine = config('vector.search.maxsim.refine', 2000);
            DB::statement("SET vchordrq.maxsim_refine = {$maxsimRefine}");
        }
    }
}
