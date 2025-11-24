<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_topics', function (Blueprint $table) {
            $table->id();
            $table->string('topic')->unique();
            $table->string('description');
            $table->timestamps();
        });

        DB::table('chat_topics')->insert([
            [
                'topic' => 'General Questions',
                'description' => 'General inquiries that do not fit into a specific category.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'Account & Login',
                'description' => 'Issues related to signing in, passwords, and account management.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'Billing & Payments',
                'description' => 'Invoices, refunds, subscription issues, and payment methods.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'Product Information',
                'description' => 'Questions about product features, specs, and availability.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'Orders & Tracking',
                'description' => 'Order status, tracking numbers, and delivery updates.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'Technical Support',
                'description' => 'Bug reports, errors, and technical problems.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'Troubleshooting',
                'description' => 'Help resolving issues or fixing common problems.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'How-To Guides',
                'description' => 'Tutorials, walkthroughs, and step-by-step instructions.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'Sales & Pre-Purchase',
                'description' => 'Questions from potential customers before buying.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'Returns & Cancellations',
                'description' => 'Return policy, cancellations, and warranty requests.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'Data & Privacy',
                'description' => 'Questions about security, GDPR, and data handling.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'topic' => 'Contact & Escalation',
                'description' => 'Requests to speak with a human or escalate a ticket.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_topics');
    }
};
