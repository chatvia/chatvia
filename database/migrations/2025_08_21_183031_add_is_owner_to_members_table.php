<?php

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->boolean('is_owner')->default(false)->after('user_id');
            $table->unique(['user_id', 'is_owner'], 'unique_owner_per_user');
        });

        $users = User::all();

        /** @var User $user */
        foreach ($users as $user) {
            Member::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'password' => bcrypt(Str::random()),
                'email_verified_at' => now(),
                'is_owner' => true,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique('unique_owner_per_user');
            $table->dropColumn('is_owner');
        });
    }
};
