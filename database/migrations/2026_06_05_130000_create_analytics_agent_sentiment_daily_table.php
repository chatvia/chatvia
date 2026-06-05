<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('analytics.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('analytics_agent_sentiment_daily', function (Blueprint $table): void {
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('user_id');
            $table->date('bucket_day');
            $table->string('sentiment', 20);
            $table->unsignedInteger('message_count')->default(0);

            $table->primary(['agent_id', 'bucket_day', 'sentiment']);
            $table->index(['user_id', 'bucket_day']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('analytics_agent_sentiment_daily');
    }
};
