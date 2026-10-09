<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Counts how often the end-of-life analysis called the model for a
 * conversation, so the hourly pass stops retrying a conversation whose
 * analysis keeps failing. Existing rows start at 0 (a constant default, so
 * Postgres adds the column without rewriting the table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->unsignedSmallInteger('analysis_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->dropColumn('analysis_attempts');
        });
    }
};
