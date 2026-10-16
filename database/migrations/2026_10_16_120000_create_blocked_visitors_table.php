<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_visitors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            // The visitor's fingerprint (VisitorFingerprint); one block per
            // visitor, so blocking again updates the row.
            $table->string('visitor_key', 64);
            // The agent the attack was on, and what the visitor used then, so
            // the list says who the block is about. Never the network address.
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            $table->foreignId('blocked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Null blocks until someone removes the block.
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'visitor_key']);
            $table->index(['workspace_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_visitors');
    }
};
