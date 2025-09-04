<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 20)->unique();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->enum('type', ['standalone', 'widget']);
            $table->string('name');
            $table->string('sub_domain')->nullable()->unique();
            $table->string('domain')->nullable();
            $table->text('system_instructions')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('collect_leads')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
