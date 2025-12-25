<?php

use App\Models\Agent;
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
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(Agent::class);
            $table->foreignIdFor(Agent::class)->nullable()->constrained()->cascadeOnDelete();

            $table->dropConstrainedForeignIdFor(User::class);
            $table->foreignIdFor(User::class)->nullable()->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(Agent::class);
            $table->foreignIdFor(Agent::class)->nullable()->constrained()->nullOnDelete();

            $table->dropConstrainedForeignIdFor(User::class);
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
        });
    }
};
