<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_website_urls', function (Blueprint $table) {
            $table->enum('status', ['pending', 'ingesting', 'ingested', 'failed'])->index()->default('pending');
            $table->string('internal_ingestion_job_id')->unique()->nullable();
            $table->string('internal_chunking_batch_id')->unique()->nullable();
        });

        $urls = DB::table('agent_website_urls')->select(['id'])->whereNotNull('crawled_at')->get();

        foreach ($urls as $url) {
            DB::table('agent_website_urls')
                ->where('id', $url->id)
                ->update(['status' => 'ingested']);
        }
    }

    public function down(): void {}
};
