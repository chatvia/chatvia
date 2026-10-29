<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->char('fingerprint', 40)->unique();
            $table->string('service', 32);
            $table->string('kind', 32);
            $table->string('subject')->nullable();
            $table->enum('status', ['open', 'resolved', 'ignored'])->default('open');
            $table->enum('resolution', ['admin', 'recovered'])->nullable();
            $table->text('message')->nullable();
            $table->unsignedBigInteger('occurrences_count')->default(0);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_seen_at']);
            $table->index(['service', 'status']);
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
