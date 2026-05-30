<?php

use App\Auth\Models\User;
use App\Billing\Models\UserCredit;
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

            // Prevent two overlapping grants per user with identical start.
            $table->unique(['user_id', 'started_at']);
            // BitmapAnd handles "active credit for user X" via the unique above
            // plus an ends_at range scan — no need for a 3-col composite.
            $table->index(['user_id', 'ends_at']);
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
            $table->string('type'); // 'extra_message_credits', 'extra_agents'
            $table->unsignedInteger('amount')->default(0);
            $table->string('stripe_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->index(['user_id', 'type', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credits');
        Schema::dropIfExists('usages');
        Schema::dropIfExists('addons');
    }
};
