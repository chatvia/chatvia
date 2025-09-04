 <?php

use App\Models\Agent;
 use App\Models\ChatConversation;
 use App\Models\ChatSession;
 use App\Models\PipelineStage;
 use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color')->default('#6B7280');
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('order');
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ChatConversation::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(PipelineStage::class)->constrained()->cascadeOnDelete();

            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('location')->nullable();

            $table->enum('status', ['new', 'contacted', 'qualified', 'converted', 'lost'])->default('new');

            $table->string('priority', 20)->default('medium'); // low, medium, high, urgent
            $table->decimal('score', 5, 2)->default(0); // Lead scoring 0-100
            $table->decimal('value', 10, 2)->nullable(); // Potential deal value

            // Tags & Labels
            $table->json('tags')->nullable(); // ["High value", "Real estate", "Demo requested"]
            $table->json('custom_fields')->nullable(); // Flexible additional data

            // Assignment & Team
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->nullable(); // widget, form, manual, import

            // Notes & Communication
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable(); // Private team notes

            // Follow-up & Tasks
            $table->timestamp('next_follow_up')->nullable();
            $table->string('follow_up_type')->nullable(); // call, email, meeting
            $table->text('follow_up_notes')->nullable();

            // Tracking & Analytics
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('referrer')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->integer('page_views')->default(0);
            $table->integer('session_duration')->nullable(); // in seconds

            // Status Timestamps
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            $table->timestamps();

            // Indexes for better performance
            $table->index(['status', 'created_at']);
            $table->index(['assigned_to', 'status']);
            $table->index(['pipeline_stage']);
            $table->index(['next_follow_up']);
            $table->index(['last_activity_at']);
        });

        // Create lead activities table for tracking all interactions
        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // status_change, note_added, email_sent, call_made, etc.
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional data like old/new values
            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
            $table->index('type');
        });

        // Create lead comments table for team collaboration
        Schema::create('lead_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->boolean('is_internal')->default(true); // Internal team comments vs customer-facing
            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
        });

        // Create lead tasks/reminders table
        Schema::create('lead_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['call', 'email', 'meeting', 'follow_up', 'demo', 'other'])->default('follow_up');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->timestamp('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->timestamps();

            $table->index(['assigned_to', 'status']);
            $table->index(['lead_id', 'due_date']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_tasks');
        Schema::dropIfExists('lead_comments');
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('pipeline_stages');
    }
};
