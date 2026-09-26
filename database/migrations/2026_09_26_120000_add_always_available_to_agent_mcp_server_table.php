<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-agent "always on" for a connector: its tools ship on every turn
        // instead of only when the relevance router picks them.
        Schema::table('agent_mcp_server', function (Blueprint $table): void {
            $table->boolean('always_available')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('agent_mcp_server', function (Blueprint $table): void {
            $table->dropColumn('always_available');
        });
    }
};
