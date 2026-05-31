<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_user', static function (Blueprint $table) {
            $table->foreignId('team_id')->constrained('teams')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnUpdate()->restrictOnDelete();

            $table->primary(['team_id', 'user_id']);

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_user');
    }
};
