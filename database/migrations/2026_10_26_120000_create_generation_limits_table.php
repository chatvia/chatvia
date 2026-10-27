<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('monthly_limit')->nullable();
            $table->unsignedInteger('generations_used')->default(0);
            $table->unsignedInteger('monthly_video_limit')->nullable();
            $table->unsignedInteger('videos_used')->default(0);
            $table->timestamp('period_starts_at');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE generation_limits SET (fillfactor = 80)');
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_limits');
    }
};
