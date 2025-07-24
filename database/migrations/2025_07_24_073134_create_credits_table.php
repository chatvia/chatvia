<?php

use App\Models\Credit;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            $table->integer('messages')->default(0);
            $table->integer('pages')->default(0);
            $table->integer('minutes')->default(0);

            $table->timestamp('started_at');
            $table->timestamp('ends_at');

            $table->unique(['user_id', 'started_at']);
            $table->unique(['user_id', 'ends_at']);
        });

        Schema::create('usages', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Credit::class)->unique()->constrained()->cascadeOnDelete();
            $table->integer('pages')->default(0);
            $table->integer('minutes')->default(0);
            $table->integer('messages')->default(0);
            $table->unsignedInteger('messages_exceeded')->default(0);
            $table->timestamps();
        });

        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('type')->index(); // 'extra_message_credits', 'extra_agents'
            $table->integer('quantity')->default(0); // e.g., 1000 messages, 1 agent
            $table->string('stripe_id')->nullable()->index(); // Links to Cashier
            $table->timestamp('started_at')->nullable(); // When add-on is active
            $table->timestamp('ends_at')->nullable(); // When add-on expires (if canceled)

            $table->unique(['user_id', 'started_at']);
            $table->unique(['user_id', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credits');
        Schema::dropIfExists('usages');
    }
};
