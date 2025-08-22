<?php

use App\Support\Agent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('external_id')->unique()->nullable()->after('id');
            $table->boolean('widget_enabled')->default(false)->index();
            $table->boolean('embed_enabled')->default(false)->index();
        });

        $agents = DB::table('agents')->select('id')->get();

        foreach ($agents as $agent) {
            $externalId = Agent::generateRandomExternalId();
            DB::table('agents')->where('id', $agent->id)->update(['external_id' => $externalId]);
        }
    }

    public function down(): void {}
};
