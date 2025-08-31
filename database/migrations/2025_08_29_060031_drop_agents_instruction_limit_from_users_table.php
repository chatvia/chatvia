<?php

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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('agents_limit')->default(0);
        });

        DB::table('users')->update([
            'agents_limit' => DB::raw('COALESCE(agents_instruction_limit, 0) + COALESCE(agents_knowledge_limit, 0)'),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('agents_instruction_limit');
            $table->dropColumn('agents_knowledge_limit');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->enum('type', ['knowledge_base', 'widget'])->index()->nullable();
            $table->string('sub_domain')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            //
        });
    }
};
