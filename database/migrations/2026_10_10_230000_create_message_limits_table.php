<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(User::class)->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('monthly_limit');
            $table->unsignedInteger('messages_used')->default(0);
            $table->timestamp('period_starts_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_limits');
    }
};
