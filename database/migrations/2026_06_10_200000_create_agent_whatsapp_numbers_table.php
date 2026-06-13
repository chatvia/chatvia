<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One WhatsApp Business number per agent (the agent's WhatsApp "channel").
 * The Cloud API access token and the Meta app secret are stored encrypted
 * (the token can send messages as the number; the secret authenticates
 * inbound webhooks). `webhook_secret` is the random value in the public
 * Callback URL — it routes an incoming event to the right agent and doubles
 * as the verify token Meta echoes during the GET webhook handshake.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_whatsapp_numbers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('agent_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('access_token'); // encrypted at the model layer
            $table->text('app_secret'); // encrypted at the model layer
            $table->string('phone_number_id'); // Cloud API phone number id — the send endpoint AND the inbound routing key
            $table->string('waba_id'); // WhatsApp Business Account id — the app must be subscribed to it or webhooks never fire
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
