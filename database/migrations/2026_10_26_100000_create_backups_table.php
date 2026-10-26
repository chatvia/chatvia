<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('status', 16)->default('pending');
            $table->string('trigger', 16);
            $table->string('disk', 32)->nullable();
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->jsonb('parts')->nullable();
            $table->string('key_fingerprint', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE backups ADD CONSTRAINT backups_status_check CHECK (status IN ('pending', 'running', 'completed', 'failed'))");
        DB::statement("ALTER TABLE backups ADD CONSTRAINT backups_trigger_check CHECK (\"trigger\" IN ('scheduled', 'manual'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
