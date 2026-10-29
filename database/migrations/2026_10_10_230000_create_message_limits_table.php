<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('chatter_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('monthly_limit')->nullable();
            $table->unsignedInteger('messages_used')->default(0);
            $table->timestamp('period_starts_at');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE message_limits ADD CONSTRAINT message_limits_one_owner CHECK (num_nonnulls(workspace_id, chatter_id) = 1)');
        DB::statement('ALTER TABLE message_limits SET (fillfactor = 80)');
    }

    public function down(): void
    {
        Schema::dropIfExists('message_limits');
    }
};
