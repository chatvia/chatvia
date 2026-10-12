<?php

use Domain\Llm\Enums\LlmProviderEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_providers', function (Blueprint $table): void {
            $table->id();

            $table->enum('kind', array_column(LlmProviderEnum::cases(), 'value'));
            $table->string('name');

            // Null uses the kind's default address.
            $table->string('base_url')->nullable();

            // Encrypted by the model.
            $table->text('api_key')->nullable();
            $table->text('headers')->nullable();

            $table->boolean('is_enabled')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_providers');
    }
};
