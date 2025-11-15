<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_youtube_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('url')->index();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->string('checksum')->nullable();
            $table->unsignedInteger('length');
            $table->enum('status', ['pending', 'ingesting', 'ingested', 'failed'])->index()->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_youtube_videos');
    }
};
