<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            // Null for a system topic every workspace can use.
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('topic');
            $table->string('description');
            $table->timestamps();

            $table->unique(['workspace_id', 'topic']);
        });

        DB::statement('CREATE UNIQUE INDEX topics_global_topic_unique ON topics (topic) WHERE workspace_id IS NULL');

        Schema::create('agent_topic', function (Blueprint $table): void {
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();

            $table->primary(['agent_id', 'topic_id']);

            $table->index('topic_id');
        });

        $systemTopics = [
            [
                'workspace_id' => null,
                'topic' => 'General Questions',
                'description' => 'General inquiries that do not fit into a specific category.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workspace_id' => null,
                'topic' => 'Account & Login',
                'description' => 'Issues related to signing in, passwords, and account management.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workspace_id' => null,
                'topic' => 'Billing & Payments',
                'description' => 'Invoices, refunds, subscription issues, and payment methods.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workspace_id' => null,
                'topic' => 'Product Information',
                'description' => 'Questions about product features, specs, and availability.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workspace_id' => null,
                'topic' => 'Orders & Tracking',
                'description' => 'Order status, tracking numbers, and delivery updates.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workspace_id' => null,
                'topic' => 'Technical Support',
                'description' => 'Bug reports, errors, and technical problems.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workspace_id' => null,
                'topic' => 'Troubleshooting',
                'description' => 'Help resolving issues or fixing common problems.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workspace_id' => null,
                'topic' => 'How-To Guides',
                'description' => 'Tutorials, walkthroughs, and step-by-step instructions.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workspace_id' => null,
                'topic' => 'Sales & Pre-Purchase',
                'description' => 'Questions from potential customers before buying.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workspace_id' => null,
                'topic' => 'Returns & Cancellations',
                'description' => 'Return policy, cancellations, and warranty requests.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workspace_id' => null,
                'topic' => 'Data & Privacy',
                'description' => 'Questions about security, GDPR, and data handling.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workspace_id' => null,
                'topic' => 'Contact & Escalation',
                'description' => 'Requests to speak with a human or escalate a ticket.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('topics')->insert(array_map(
            static fn (array $topic): array => [...$topic, 'external_id' => (string) Str::uuid7()],
            $systemTopics,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_topic');
        Schema::dropIfExists('topics');
    }
};
