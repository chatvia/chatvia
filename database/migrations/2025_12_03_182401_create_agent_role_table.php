<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();

            $table->primary(['role_id', 'agent_id']);

            $table->index('agent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_role');
    }
};
