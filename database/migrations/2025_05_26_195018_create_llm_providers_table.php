<?php

use Domain\Llm\Enums\LlmProviderKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_providers', function (Blueprint $table): void {
            $table->id();

            $table->enum('kind', array_column(LlmProviderKind::cases(), 'value'));
            $table->string('name');

            // A server of your own only: every other kind calls with the
            // address and key of its account (Settings → AI providers).
            $table->string('base_url')->nullable();

            // Encrypted by the model.
            $table->text('api_key')->nullable();
            $table->text('headers')->nullable();

            $table->boolean('is_enabled')->default(true);

            $table->timestamps();
        });

        DB::statement("ALTER TABLE llm_providers ADD CONSTRAINT llm_providers_key_on_custom_only CHECK (kind = 'custom' OR (api_key IS NULL AND base_url IS NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_providers');
    }
};
