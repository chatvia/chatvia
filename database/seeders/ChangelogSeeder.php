<?php

namespace Database\Seeders;

use App\Changelog\Models\Changelog;
use App\Changelog\Models\ChangelogItem;
use Illuminate\Database\Seeder;

class ChangelogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $versions = config('changelog.versions', []);

        foreach ($versions as $versionData) {
            $changelog = Changelog::create([
                'version' => $versionData['version'],
                'release_date' => \Carbon\Carbon::parse($versionData['release_date']),
            ]);

            $changes = $versionData['changes'] ?? [];

            foreach ($changes as $type => $items) {
                foreach ($items as $description) {
                    ChangelogItem::create([
                        'changelog_id' => $changelog->id,
                        'type' => $type,
                        'description' => $description,
                    ]);
                }
            }
        }
    }
}
