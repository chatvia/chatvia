<?php

use App\Models\User;
use App\Models\UserCredit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            $table->unsignedInteger('messages')->default(0);

            $table->timestamp('started_at');

            $table->timestamp('ends_at');

            $table->unique(['user_id', 'started_at']);
            $table->unique(['user_id', 'ends_at']);
            $table->index(['user_id', 'started_at', 'ends_at']);
        });

        Schema::create('user_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(UserCredit::class)->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('messages')->default(0);
            $table->unsignedInteger('credits')->default(0);
            $table->unsignedInteger('messages_exceeded')->default(0);
            $table->timestamps();
        });

        Schema::create('user_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('type')->index(); // 'extra_message_credits', 'extra_agents'
            $table->unsignedInteger('amount')->default(0); // e.g., 1000 messages, 1 agent
            $table->string('stripe_id')->nullable()->index(); // Links to Cashier
            $table->timestamp('started_at')->nullable(); // When add-on is active
            $table->timestamp('ends_at')->nullable(); // When add-on expires (if canceled)

            $table->unique(['user_id', 'started_at']);
            $table->unique(['user_id', 'ends_at']);
            $table->index(['user_id', 'type', 'started_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credits');
        Schema::dropIfExists('usages');
        Schema::dropIfExists('addons');
    }
};
