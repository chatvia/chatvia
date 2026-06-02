<?php

use App\Workspace\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->nullable()->constrained()->cascadeOnDelete();
            $table->string('topic');
            $table->string('description');
            $table->timestamps();

            $table->unique(['user_id', 'topic']);
        });

        DB::statement('CREATE UNIQUE INDEX chat_topics_global_topic_unique ON chat_topics (topic) WHERE user_id IS NULL');

        Schema::create('agent_topic', function (Blueprint $table) {
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_topic_id')->constrained('chat_topics')->cascadeOnDelete();

            $table->primary(['agent_id', 'chat_topic_id']);

            $table->index('chat_topic_id');
        });

        DB::table('chat_topics')->insert([
            [
                'user_id' => null,
                'topic' => 'General Questions',
                'description' => 'General inquiries that do not fit into a specific category.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => null,
                'topic' => 'Account & Login',
                'description' => 'Issues related to signing in, passwords, and account management.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => null,
                'topic' => 'Billing & Payments',
                'description' => 'Invoices, refunds, subscription issues, and payment methods.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => null,
                'topic' => 'Product Information',
                'description' => 'Questions about product features, specs, and availability.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => null,
                'topic' => 'Orders & Tracking',
                'description' => 'Order status, tracking numbers, and delivery updates.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => null,
                'topic' => 'Technical Support',
                'description' => 'Bug reports, errors, and technical problems.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => null,
                'topic' => 'Troubleshooting',
                'description' => 'Help resolving issues or fixing common problems.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => null,
                'topic' => 'How-To Guides',
                'description' => 'Tutorials, walkthroughs, and step-by-step instructions.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => null,
                'topic' => 'Sales & Pre-Purchase',
                'description' => 'Questions from potential customers before buying.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => null,
                'topic' => 'Returns & Cancellations',
                'description' => 'Return policy, cancellations, and warranty requests.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => null,
                'topic' => 'Data & Privacy',
                'description' => 'Questions about security, GDPR, and data handling.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => null,
                'topic' => 'Contact & Escalation',
                'description' => 'Requests to speak with a human or escalate a ticket.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_topic');
        Schema::dropIfExists('chat_topics');
    }
};
