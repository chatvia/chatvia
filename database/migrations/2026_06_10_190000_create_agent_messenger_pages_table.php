<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One Facebook Page per agent (the agent's Messenger "channel"). The page
 * access token and the Meta app secret are stored encrypted (the token can
 * send messages as the page; the secret authenticates inbound webhooks).
 * `webhook_secret` is the random value in the public Callback URL — it routes
 * an incoming event to the right agent and doubles as the verify token Meta
 * echoes during the GET webhook handshake.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_messenger_pages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('agent_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('page_access_token'); // encrypted at the model layer
            $table->text('app_secret'); // encrypted at the model layer
            $table->string('page_id')->nullable(); // the Facebook Page id — used to ignore the page's own messages
            $table->string('page_name')->nullable();
            $table->string('webhook_secret', 64)->unique();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_messenger_pages');
    }
};
