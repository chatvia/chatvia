<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserCredit;
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
            $table->string('type');
            $table->unsignedInteger('amount')->default(0);
            $table->string('stripe_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->index(['user_id', 'type', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_usages');
        Schema::dropIfExists('user_addons');
        Schema::dropIfExists('user_credits');
    }
};
