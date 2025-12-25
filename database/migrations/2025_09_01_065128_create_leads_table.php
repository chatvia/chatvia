 <?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('chat_conversation_id')->constrained('chat_conversations')->cascadeOnDelete();

            // Core contact info
            $table->string('name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 50)->nullable()->index();
            $table->string('job_title')->nullable();
            $table->string('company')->nullable();
            $table->string('location')->nullable();
            $table->text('message')->nullable();

            $table->string('referrer_url')->nullable();
            $table->string('page_url')->nullable();
            $table->string('locale', 10)->nullable();

            $table->ipAddress()->nullable();

            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->unique(['agent_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
