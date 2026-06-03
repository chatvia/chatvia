<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Support\Cms\Changelog\Models\Changelog;
use Support\Cms\Changelog\Models\ChangelogItem;

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
                'release_date' => Carbon::parse($versionData['release_date']),
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
