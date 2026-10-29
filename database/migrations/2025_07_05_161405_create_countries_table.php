<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });

        $countriesJson = File::get(database_path('data/countries.json'));
        $countries = json_decode($countriesJson, true);

        foreach ($countries as $country) {
            DB::table('countries')->insert([
                'name' => $country['name'],
                'code' => Str::lower($country['code']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
