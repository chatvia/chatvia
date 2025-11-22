<?php

use App\Models\User;
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
        Schema::create('source_youtube_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('url')->index();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->string('checksum')->nullable();
            $table->unsignedInteger('length');
            $table->enum('status', ['pending', 'ingesting', 'ingested', 'failed'])->index()->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'checksum']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_youtube_videos');
    }
};
