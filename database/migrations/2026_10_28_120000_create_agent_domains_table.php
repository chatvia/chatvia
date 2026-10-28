<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('host', 253)->unique();
            $table->string('verification_token', 64);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_check')->nullable();
            $table->unsignedSmallInteger('failed_checks')->default(0);
            $table->timestamps();
        });

        // Hosts are saved lowercase, so the unique index also catches a
        // mixed-case copy. `last_check` follows Domain\Agent\Enums\DomainCheck.
        DB::statement('ALTER TABLE agent_domains ADD CONSTRAINT agent_domains_host_lowercase CHECK (host = lower(host))');
        DB::statement("ALTER TABLE agent_domains ADD CONSTRAINT agent_domains_last_check_check CHECK (last_check IN ('reached', 'no_certificate', 'points_elsewhere', 'not_reached', 'private_address'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_domains');
    }
};
