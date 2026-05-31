<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('changelog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('changelog_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->text('description');
            $table->timestamps();
        });

        $this->seedChangelogData();
    }

    public function down(): void
    {
        Schema::dropIfExists('changelog_items');
    }

    private function seedChangelogData(): void
    {
        $now = now();

        $changelog1 = DB::table('changelogs')->insertGetId([
            'version' => '08-09-2025',
            'release_date' => '2025-09-08 00:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('changelog_items')->insert([
            ['changelog_id' => $changelog1, 'type' => 'new_features', 'description' => 'New modern design with improved user experience', 'created_at' => $now, 'updated_at' => $now],
            ['changelog_id' => $changelog1, 'type' => 'new_features', 'description' => 'Chat logs - view and manage all conversations with your agents', 'created_at' => $now, 'updated_at' => $now],
            ['changelog_id' => $changelog1, 'type' => 'new_features', 'description' => 'Lead collection - capture visitor information through chat widgets', 'created_at' => $now, 'updated_at' => $now],
            ['changelog_id' => $changelog1, 'type' => 'new_features', 'description' => 'Enhanced widget customization options with color themes and positioning', 'created_at' => $now, 'updated_at' => $now],
            ['changelog_id' => $changelog1, 'type' => 'improvements', 'description' => 'Better widget performance and responsiveness', 'created_at' => $now, 'updated_at' => $now],
            ['changelog_id' => $changelog1, 'type' => 'improvements', 'description' => 'Improved chat interface with better message handling', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $changelog2 = DB::table('changelogs')->insertGetId([
            'version' => '31-08-2025',
            'release_date' => '2025-08-31 00:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('changelog_items')->insert([
            ['changelog_id' => $changelog2, 'type' => 'new_features', 'description' => 'Chat widget for your website', 'created_at' => $now, 'updated_at' => $now],
            ['changelog_id' => $changelog2, 'type' => 'new_features', 'description' => 'User interface refinements with additional customization options', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $changelog3 = DB::table('changelogs')->insertGetId([
            'version' => '22-08-2025',
            'release_date' => '2025-08-22 00:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('changelog_items')->insert([
            ['changelog_id' => $changelog3, 'type' => 'new_features', 'description' => 'Chat preview added directly in the agent control panel, users can now chat with the agent without leaving the panel', 'created_at' => $now, 'updated_at' => $now],
            ['changelog_id' => $changelog3, 'type' => 'new_features', 'description' => 'Embedding via iframe is now supported, easily integrate ChatVia into your own website', 'created_at' => $now, 'updated_at' => $now],
            ['changelog_id' => $changelog3, 'type' => 'new_features', 'description' => 'User interface refinements with additional customization options', 'created_at' => $now, 'updated_at' => $now],
            ['changelog_id' => $changelog3, 'type' => 'new_features', 'description' => 'New dedicated tab for Security settings', 'created_at' => $now, 'updated_at' => $now],
            ['changelog_id' => $changelog3, 'type' => 'new_features', 'description' => 'New dedicated tab for Persona management', 'created_at' => $now, 'updated_at' => $now],
            ['changelog_id' => $changelog3, 'type' => 'new_features', 'description' => 'Performance improvements', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $changelog4 = DB::table('changelogs')->insertGetId([
            'version' => '14-08-2025',
            'release_date' => '2025-08-14 00:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('changelog_items')->insert([
            ['changelog_id' => $changelog4, 'type' => 'new_features', 'description' => 'Added support for Right-to-Left (RTL) languages', 'created_at' => $now, 'updated_at' => $now],
            ['changelog_id' => $changelog4, 'type' => 'new_features', 'description' => 'Added Arabic font', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $changelog5 = DB::table('changelogs')->insertGetId([
            'version' => '13-08-2025',
            'release_date' => '2025-08-13 00:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('changelog_items')->insert([
            ['changelog_id' => $changelog5, 'type' => 'new_features', 'description' => 'Added LaTeX rendering for math equations', 'created_at' => $now, 'updated_at' => $now],
            ['changelog_id' => $changelog5, 'type' => 'improvements', 'description' => 'Better support for PDFs with complex layouts', 'created_at' => $now, 'updated_at' => $now],
            ['changelog_id' => $changelog5, 'type' => 'improvements', 'description' => 'Better support for files with multiple languages', 'created_at' => $now, 'updated_at' => $now],
            ['changelog_id' => $changelog5, 'type' => 'bug_fixes', 'description' => 'Fixed a bug with PDF parsing and extraction', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $changelog6 = DB::table('changelogs')->insertGetId([
            'version' => '11-08-2025',
            'release_date' => '2025-08-11 00:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('changelog_items')->insert([
            ['changelog_id' => $changelog6, 'type' => 'core_features', 'description' => 'Initial release of Chatvia', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
};
