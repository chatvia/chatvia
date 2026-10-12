<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
            // Platform staff: may open the admin panel. Everything else about an
            // account (owner or teammate) is its role in `workspace_user`.
            $table->boolean('is_admin')->default(false);
            $table->rememberToken();

            $table->foreignId('current_team_id')
                ->nullable()
                ->index()
                ->constrained('teams')
                ->nullOnDelete();

            $table->ipAddress('registered_ip')->nullable();
            $table->string('registered_country_code', 2)->nullable();

            $table->string('profile_image_path')->nullable();

            $table->string('deactivation_reason')->nullable();

            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('last_login_at')->nullable();

            $table->string('date_format')->nullable();

            $table->string('locale', 5)->nullable();

            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            // A Unix time: 64-bit, so it does not overflow in 2038.
            $table->bigInteger('last_activity')->index();

            $table->index(['user_id', 'last_activity']);
        });

        // The hosted chat's own sessions (/chat runs on its own cookie). Kept
        // apart from `sessions` because `user_id` here may hold a chatter id or
        // a staff id, so it has no foreign key.
        Schema::create('chat_web_sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            // A Unix time: 64-bit, so it does not overflow in 2038.
            $table->bigInteger('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('chat_web_sessions');
    }
};
