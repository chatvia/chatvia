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
            // SET NULL, not CASCADE: a captured lead is standalone CRM data and
            // must survive the agents → chat_sessions → chat_conversations
            // cascade chain (agent or chatter deletion would otherwise
            // hard-delete the whole lead book through this FK).
            $table->foreignId('chat_conversation_id')->nullable()->index()->constrained('chat_conversations')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->index()->constrained('users')->nullOnDelete();

            $table->enum('status', ['new', 'contacted', 'qualified', 'converted', 'lost'])->default('new');

            // PII + message are encrypted at rest (Lead::casts), so these are
            // text (ciphertext overflows varchar) and not directly queryable.
            $table->text('name')->nullable();
            $table->text('email')->nullable();
            $table->string('email_hash')->nullable(); // HMAC blind index for dedup/lookup
            $table->text('phone')->nullable();
            $table->text('job_title')->nullable();
            $table->text('company')->nullable();
            $table->text('location')->nullable();
            $table->text('message')->nullable();

            $table->string('referrer_url')->nullable();
            $table->string('page_url')->nullable();
            $table->string('locale', 10)->nullable();

            $table->ipAddress()->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('contacted_at')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'email_hash']);
            $table->index(['agent_id', 'created_at']);
            $table->index(['agent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
