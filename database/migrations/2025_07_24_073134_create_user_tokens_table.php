<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserToken;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A workspace's token allowance for one billing cycle. Tokens are the
        // single credit currency — the send-gate is simply "balance > 0".
        // Non-accumulative: each grant expires the prior cycle (see
        // UserTokenSystem::addCredit).
        Schema::create('user_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            // The granted per-cycle token budget. Source of truth:
            // config billing.packages.*.quota_limits.*.tokens_limit.
            $table->unsignedBigInteger('tokens')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('ends_at');

            $table->unique(['user_id', 'started_at']);
            $table->index(['user_id', 'ends_at']);
        });

        // Tokens consumed in the cycle (real prompt + completion from the
        // provider; TikToken estimate as a fallback). Accumulator only — it is
        // ALLOWED to exceed the budget: the turn that crosses zero completes and
        // overshoots, and the available balance is clamped at 0 so the next turn
        // is blocked (soft overage).
        Schema::create('user_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(UserToken::class)->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tokens')->default(0);
            $table->timestamps();
        });

        // Purchasable token top-ups (manual extra packs + auto-recharge). `amount`
        // is in TOKENS. A pack expires 30 days after purchase (ends_at),
        // independent of the base cycle, and is summed into the available balance
        // while active.
        Schema::create('user_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('stripe_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->index(['user_id', 'type', 'ends_at']);
        });

        // Idempotency ledger for token consumption: one row per assistant turn
        // (unique turn_key = assistant message id). A retried RecordTokenUsageJob
        // hits the unique constraint and skips the re-charge. Append-only;
        // survives cache eviction (unlike a cache-key guard).
        Schema::create('token_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('turn_key')->unique();
            $table->unsignedBigInteger('tokens');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_charges');
        Schema::dropIfExists('user_usages');
        Schema::dropIfExists('user_addons');
        Schema::dropIfExists('user_tokens');
    }
};
