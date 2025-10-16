<?php

use App\Models\AgentWebsite;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('links', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(AgentWebsite::class)->constrained()->cascadeOnDelete();
            $table->text('link');
            $table->text('found_on_url')->nullable();
            $table->text('text')->nullable();
            $table->string('hash');
            $table->timestamps();
            $table->timestamp('crawled_at')->nullable();
        });

        Schema::table('agent_websites', function (Blueprint $table) {
            $table->dropColumn('links');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('links');
    }
};
