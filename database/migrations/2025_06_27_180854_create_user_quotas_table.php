<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            // Pages quota
            $table->unsignedInteger('pages_limit')->default(0)->comment('Pages allowed for billing period');
            $table->unsignedInteger('pages_used')->default(0)->comment('Pages used in current billing period');
            $table->unsignedInteger('pages_removed')->default(0)->comment('Pages removed in current billing period');

            // Minutes quota
            $table->unsignedInteger('minutes_limit')->default(0)->comment('Minutes allowed for billing period');
            $table->unsignedInteger('minutes_used')->default(0)->comment('Minutes used in current billing period');
            $table->unsignedInteger('minutes_removed')->default(0)->comment('Minutes removed in current billing period');

            // Messages quota
            $table->unsignedInteger('messages_limit')->default(0)->comment('Messages allowed for billing period');
            $table->unsignedInteger('messages_used')->default(0)->comment('Messages used in current billing period');
            $table->unsignedInteger('messages_removed')->default(0)->comment('Messages removed in current billing period');

            // Agents quota
            $table->unsignedInteger('agents_instruction_limit')->default(0)->comment('Agent instruction limits for billing period');
            $table->unsignedInteger('agents_knowledge_limit')->default(0)->comment('Agent knowledge limits for billing period');

            $table->unsignedInteger('agents_instruction_removed')->default(0)->comment('Agent instructions removed in current billing period');
            $table->unsignedInteger('agents_knowledge_removed')->default(0)->comment('Agent knowledge removed in current billing period');

            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly')->comment('Current billing cycle type (monthly or yearly)');

            $table->boolean('is_paid_plan')->default(false)->comment('Whether the user is on a paid plan or not');

            // Billing period tracking
            $table->timestamp('billing_period_starts_at')->nullable()->comment('Current billing period start');
            $table->timestamp('billing_period_ends_at')->nullable()->comment('Current billing period end');

            $table->timestamps();

            // Performance indexes
            $table->index(['pages_used', 'pages_limit'], 'idx_pages_quota');
            $table->index(['minutes_used', 'minutes_limit'], 'idx_minutes_quota');
            $table->index(['messages_used', 'messages_limit'], 'idx_messages_quota');
            $table->index('billing_period_ends_at', 'idx_billing_period_end');
            $table->index(['user_id', 'billing_period_ends_at'], 'idx_user_billing_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_quotas');
    }
};
