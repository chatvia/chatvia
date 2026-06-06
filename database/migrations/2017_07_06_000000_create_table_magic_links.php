<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('magiclink.magiclink_table', 'magic_links'), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('token', 255);
            $table->text('action');
            $table->string('access_code')->nullable();
            $table->unsignedTinyInteger('num_visits')->default(0);
            $table->unsignedTinyInteger('max_visits')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamps();

            $table->index('available_at');
            $table->index(['max_visits', 'num_visits']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('magiclink.magiclink_table', 'magic_links'));
    }
};
