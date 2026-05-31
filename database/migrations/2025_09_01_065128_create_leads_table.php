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
            $table->foreignId('chat_conversation_id')->index()->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->index()->constrained('users')->nullOnDelete();

            $table->enum('status', ['new', 'contacted', 'qualified', 'converted', 'lost'])->default('new');

            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('job_title')->nullable();
            $table->string('company')->nullable();
            $table->string('location')->nullable();
            $table->text('message')->nullable();

            $table->string('referrer_url')->nullable();
            $table->string('page_url')->nullable();
            $table->string('locale', 10)->nullable();

            $table->ipAddress()->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('contacted_at')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'email']);
            $table->index(['agent_id', 'created_at']);
            $table->index(['agent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
