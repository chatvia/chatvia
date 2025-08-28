<?php

use App\Models\AgentFile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chunks', function (Blueprint $table) {
            $table->string('chunkable_type')->nullable();
            $table->unsignedBigInteger('chunkable_id')->nullable();
            $table->index(['chunkable_type', 'chunkable_id']);
            $table->unique(['chunkable_type', 'chunkable_id', 'md5_hash']);
        });

        DB::table('chunks')->update([
            'chunkable_type' => AgentFile::class,
            'chunkable_id' => DB::raw('agent_file_id'),
        ]);

        Schema::table('chunks', function (Blueprint $table) {
            $table->string('chunkable_type')->nullable(false)->change();
            $table->unsignedBigInteger('chunkable_id')->nullable(false)->change();
        });

        Schema::table('chunks', function (Blueprint $table) {
            $table->dropColumn('agent_file_id');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
