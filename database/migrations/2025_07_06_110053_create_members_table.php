<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            $table->string('name');

            $table->string('email');
            $table->string('password');

            $table->rememberToken();

            $table->uuid('verification_token')->unique()->nullable();

            $table->string('facebook_id')->nullable();
            $table->string('google_id')->nullable();

            $table->boolean('is_owner')->default(false);

            $table->timestamp('email_verified_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'is_owner']);
            $table->unique(['email', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
