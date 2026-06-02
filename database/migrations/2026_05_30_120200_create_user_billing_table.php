<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_billing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('stripe_id')->nullable();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            $table->boolean('auto_recharge_enabled')->default(false);
            $table->unsignedInteger('auto_recharge_threshold')->default(1000);
            $table->unsignedInteger('auto_recharge_amount')->default(1000);
            $table->timestamp('auto_recharge_last_triggered_at')->nullable();

            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX user_billing_stripe_id_unique ON user_billing (stripe_id) WHERE stripe_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_billing');
    }
};
