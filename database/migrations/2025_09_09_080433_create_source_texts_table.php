<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_texts', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            $table->string('language')->nullable();
            $table->string('document_classification')->nullable();

            $table->text('content');

            $table->unsignedInteger('length')->default(0)->comment('This is the number of pages not characters in the text');

            $table->unsignedInteger('expected_chunks')->nullable();

            $table->string('checksum');

            $table->string('internal_ingestion_job_id')->unique()->nullable();
            $table->string('internal_chunking_batch_id')->unique()->nullable();

            $table->enum('status', ['pending', 'ingesting', 'failed', 'ingested'])->default('pending')->index();
            $table->unsignedInteger('took_in_seconds')->default(0);
            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'checksum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_texts');
    }
};
