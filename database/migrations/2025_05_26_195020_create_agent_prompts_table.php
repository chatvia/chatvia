<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_prompts', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignIdFor(User::class)->index()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('prompt');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_prompts');
    }
};
