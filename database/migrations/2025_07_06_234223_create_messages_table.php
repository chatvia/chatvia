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
            $table->uuid('id')->primary();
            $table->uuid('conversation_id')->index();
            $table->string('generation_id')->nullable();

            $table->foreignIdFor(Agent::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Member::class)->nullable()->constrained()->nullOnDelete();

            $table->text('content');

            $table->enum('role', ['user', 'assistant'])->index();
            $table->string('model')->nullable();

            $table->enum('feedback', ['positive', 'negative'])->nullable();
            $table->bigInteger('response_time_ms')->nullable();
            $table->integer('token_count')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->timestamp('expires_at')->nullable();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['agent_id', 'created_at']);
        });

        // PostgreSQL-specific date indexes for optimal performance
        DB::statement('CREATE INDEX messages_created_at_date_idx ON messages USING btree (date(created_at))');
        DB::statement('CREATE INDEX messages_created_at_year_month_idx ON messages USING btree (extract(year from created_at), extract(month from created_at))');
        DB::statement('CREATE INDEX messages_agent_created_date_idx ON messages USING btree (agent_id, date(created_at))');
        DB::statement('CREATE INDEX messages_role_created_date_idx ON messages USING btree (role, date(created_at))');

    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
