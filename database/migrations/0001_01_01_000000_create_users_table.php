<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('name');
            // Nullable so API-provisioned chatters (no email at sign-up) can
            // still own a users row. Unique constraint ignores NULLs in pgsql.
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->enum('type', ['admin', 'workspace', 'employee', 'chatter'])->default('workspace')->index();
            $table->enum('source', ['dashboard', 'api'])->default('dashboard')->index();
            $table->rememberToken();

            $table->foreignId('workspace_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('current_team_id')
                ->nullable()
                ->index()
                ->constrained('teams')
                ->nullOnDelete();

            $table->index(['workspace_id', 'type']);

            $table->ipAddress('registered_ip')->nullable();
            $table->char('registered_country_code', 5)->nullable();

            $table->string('domain')->nullable();

            // Mirrors the address Stripe stores on the customer record. Kept
            // locally so the billing settings page can render it without a
            // round-trip to Stripe; written by UpdateBillingInformationAction.
            $table->jsonb('billing_address')->nullable();

            $table->string('deactivation_reason')->nullable();

            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();

            $table->index(['user_id', 'last_activity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
