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
        Schema::table('source_texts', function (Blueprint $table) {
            $table->uuid('external_id')->index()->nullable();
        });

        Schema::table('source_files', function (Blueprint $table) {
            $table->uuid('external_id')->index()->nullable();
        });

        Schema::table('source_websites', function (Blueprint $table) {
            $table->uuid('external_id')->index()->nullable();
        });

        DB::table('source_texts')->select('id')->get()->each(function ($sourceText) {
            DB::table('source_texts')->where('id', $sourceText->id)->update([
                'external_id' => \Illuminate\Support\Str::uuid()->toString(),
            ]);
        });

        DB::table('source_files')->select('id')->get()->each(function ($sourceText) {
            DB::table('source_files')->where('id', $sourceText->id)->update([
                'external_id' => \Illuminate\Support\Str::uuid()->toString(),
            ]);
        });

        DB::table('source_websites')->select('id')->get()->each(function ($sourceText) {
            DB::table('source_websites')->where('id', $sourceText->id)->update([
                'external_id' => \Illuminate\Support\Str::uuid()->toString(),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            //
        });
    }
};
