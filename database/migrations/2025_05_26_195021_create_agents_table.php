<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sub_domain')->unique();
            $table->string('domain')->nullable();
            $table->text('system_instructions');
            $table->boolean('is_public')->default(false);
            $table->boolean('is_active')->default(true);
            $table->enum('type', ['knowledge', 'instruction']);
            $table->unsignedInteger('messages_used_all_time')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
