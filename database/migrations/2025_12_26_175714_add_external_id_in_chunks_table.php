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
        Schema::table('chunks', function (Blueprint $table) {
            $table->uuid('external_id')->unique()->nullable();
        });

        foreach (\Illuminate\Support\Facades\DB::table('chunks')->select('id')->get() as $item) {
            \Illuminate\Support\Facades\DB::table('chunks')->where('id', $item->id)->update([
                'external_id' => \Illuminate\Support\Str::uuid()->toString()
            ]);
        }

        Schema::table('chunks', function (Blueprint $table) {
            $table->uuid('external_id')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chunks', function (Blueprint $table) {
            //
        });
    }
};
