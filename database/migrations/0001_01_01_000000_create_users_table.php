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
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_admin')->index()->default(false);
            $table->rememberToken();
            $table->string('stripe_id')->after('remember_token')->nullable()->index();
            $table->string('pm_type')->after('stripe_id')->nullable();
            $table->string('pm_last_four', 4)->after('pm_type')->nullable();
            $table->timestamp('trial_ends_at')->after('pm_last_four')->nullable();

            $table->string('facebook_id')->index()->nullable();
            $table->string('google_id')->index()->nullable();

            $table->ipAddress('registered_ip')->nullable();
            $table->char('country_code', 5)->nullable();

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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
