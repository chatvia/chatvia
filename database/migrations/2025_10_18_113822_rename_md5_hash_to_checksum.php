<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agent_texts', function (Blueprint $table) {
            $table->dropUnique('agent_texts_agent_id_md5_hash_unique');
            $table->renameColumn('md5_hash', 'checksum');
            $table->unique(['agent_id', 'checksum']);
        });

        Schema::table('agent_files', function (Blueprint $table) {
            $table->dropUnique('agent_files_agent_id_md5_hash_unique');
            $table->renameColumn('md5_hash', 'checksum');
            $table->unique(['agent_id', 'checksum']);
        });

        Schema::table('agent_websites', function (Blueprint $table) {
            $table->dropColumn('length');
        });

        Schema::table('links', function (Blueprint $table) {
            $table->dropColumn('found_on_url', 'text');
            $table->renameColumn('hash', 'checksum');
            $table->renameColumn('link', 'url');
            $table->string('checksum')->nullable()->change();
        });

        Schema::rename('links', 'agent_website_urls');

        Schema::table('chunks', function (Blueprint $table) {
            $table->dropUnique('chunks_chunkable_type_chunkable_id_md5_hash_unique');
            $table->renameColumn('md5_hash', 'checksum');
            $table->unique(['chunkable_type', 'chunkable_id', 'checksum']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
