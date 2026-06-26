<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_whatsapp_numbers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('agent_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('access_token');
            $table->text('app_secret');
            $table->string('phone_number_id');
            $table->string('waba_id');
            $table->string('display_phone_number')->nullable();
            $table->string('verified_name')->nullable();
            $table->string('webhook_secret', 64)->unique();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index('phone_number_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_whatsapp_numbers');
    }
};
