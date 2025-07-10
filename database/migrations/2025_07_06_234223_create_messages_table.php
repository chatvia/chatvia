<?php

use App\Models\Agent;
use App\Models\Member;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Member::class)->constrained()->nullOnDelete();
            $table->text('query');
            $table->string('model')->index();
            $table->enum('feedback', ['positive', 'negative'])->index()->nullable();
            $table->unsignedBigInteger('response_time_in_ms')->index()->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
