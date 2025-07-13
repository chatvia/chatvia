<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            $table->unsignedInteger('pages_limit')->default(0)->comment('Total pages allowed for the billing period');
            $table->unsignedInteger('pages_used')->default(0)->comment('Pages used in current billing period');

            $table->unsignedInteger('pages_used_all_time')->default(0)->comment('Pages used in current billing period');

            $table->unsignedInteger('minutes_limit')->default(0)->comment('Total pages allowed for the billing period');
            $table->unsignedInteger('minutes_used')->default(0)->comment('Pages used in current billing period');

            $table->unsignedInteger('minutes_used_all_time')->default(0)->comment('Pages used in current billing period');

            $table->unsignedInteger('messages_limit')->default(0)->comment('Messages allowed for the billing period');
            $table->unsignedInteger('messages_used')->default(0)->comment('Messages allowed for the billing period');

            $table->unsignedInteger('messages_used_all_time')->default(0)->comment('Messages allowed for the billing period');

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_quotes');
    }
};
