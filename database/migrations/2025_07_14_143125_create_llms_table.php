<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llms', function (Blueprint $table) {
            $table->id();

            // Identifiers
            $table->string('internal_name')->unique()->index();
            $table->string('external_name')->unique()->index();

            // Display information
            $table->string('descriptive_name'); // Maps to config 'name'
            $table->text('description'); // Changed to text for longer descriptions
            $table->string('icon');

            // Technical specifications
            $table->unsignedInteger('context_window'); // Maps to config 'context' - token limit
            $table->jsonb('features'); // Features like ['fast', 'advanced', 'pro']
            $table->enum('location', ['us', 'eu', 'cn'])->index();
            $table->boolean('reasoning')->default(false);
            $table->boolean('requires_paid_plan')->index();

            $table->boolean('is_default')->default(false);

            $table->timestamps();
        });

        $dataToInsert = [];

        $llms = config('llms');

        collect($llms)->each(function ($llmData) use (&$dataToInsert) {
            $dataToInsert[] = [
                'internal_name' => $llmData['internal_name'],
                'external_name' => $llmData['external_name'],
                'descriptive_name' => $llmData['descriptive_name'],
                'description' => $llmData['description'],
                'icon' => $llmData['icon'],
                'context_window' => $llmData['context_window'],
                'features' => json_encode($llmData['features']),
                'location' => $llmData['location'],
                'requires_paid_plan' => $llmData['requires_paid_plan'],
                'reasoning' => $llmData['reasoning'],
                'is_default' => $llmData['default'] ?? false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        });

        DB::table('llms')->insert($dataToInsert);
    }

    public function down(): void
    {
        Schema::dropIfExists('llms');
    }
};
