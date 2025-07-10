<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('native');
            $table->string('code')->unique();
            $table->timestamps();
        });

        $languagesJson = File::get(database_path('data/languages.json'));
        $languages = json_decode($languagesJson, true);

        foreach ($languages as $language) {
            DB::table('languages')->insert([
                'name' => $language['name'],
                'native' => $language['native'],
                'code' => $language['code'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
