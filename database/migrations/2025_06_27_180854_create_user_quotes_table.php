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
            $table->foreignIdFor(User::class)->unique()->constrained()->cascadeOnDelete();

            // Pages quota
            $table->unsignedInteger('pages_limit')->index()->default(0)->comment('Pages allowed for billing period');
            $table->unsignedInteger('pages_used')->default(0)->comment('Pages used in current billing period');
            $table->unsignedInteger('pages_used_all_time')->default(0)->comment('Total pages used historically');

            // Minutes quota
            $table->unsignedInteger('minutes_limit')->index()->default(0)->comment('Minutes allowed for billing period');
            $table->unsignedInteger('minutes_used')->default(0)->comment('Minutes used in current billing period');
            $table->unsignedInteger('minutes_used_all_time')->default(0)->comment('Total minutes used historically');

            // Messages quota
            $table->unsignedInteger('messages_limit')->index()->default(0)->comment('Messages allowed for billing period');
            $table->unsignedInteger('messages_used')->default(0)->comment('Messages used in current billing period');
            $table->unsignedInteger('messages_used_all_time')->default(0)->comment('Total messages used historically');

            // Billing period tracking
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();

            $table->timestamps();
        });

        // PostgreSQL-specific date indexes for optimal performance
        DB::statement('CREATE INDEX user_quotas_user_period_start_date_idx ON user_quotas (user_id, date(current_period_starts_at))');
        DB::statement('CREATE INDEX user_quotas_user_period_end_date_idx ON user_quotas (user_id, date(current_period_ends_at))');
        DB::statement('CREATE INDEX user_quotas_period_year_month_idx ON user_quotas (extract(year from current_period_starts_at), extract(month from current_period_starts_at))');
        DB::statement('CREATE INDEX user_quotas_active_period_idx ON user_quotas (date(current_period_starts_at), date(current_period_ends_at)) WHERE current_period_starts_at IS NOT NULL');

    }

    public function down(): void
    {
        Schema::dropIfExists('user_quotas');
    }
};
