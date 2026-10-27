<?php

namespace Database\Factories;

use App\Admin\Backup\Enums\BackupPart;
use App\Admin\Backup\Enums\BackupStatus;
use App\Admin\Backup\Enums\BackupTrigger;
use App\Admin\Backup\Models\Backup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a scheduled backup waiting for a worker; `completed()` gives a
 * stored one.
 *
 * @extends Factory<Backup>
 */
class BackupFactory extends Factory
{
    #[\Override]
    protected $model = Backup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => BackupStatus::Pending,
            'trigger' => BackupTrigger::Scheduled,
        ];
    }

    /** A stored backup of the database and Redis, made at $at (default now). */
    public function completed(?\DateTimeInterface $at = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BackupStatus::Completed,
            'disk' => 'backups',
            'path' => 'backups/chatvia-'.fake()->unique()->numerify('##########').'.tar',
            'size' => 1024,
            'sha256' => hash('sha256', fake()->uuid()),
            'parts' => [BackupPart::Database, BackupPart::Redis],
            'started_at' => $at ?? now(),
            'finished_at' => $at ?? now(),
            'created_at' => $at ?? now(),
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn (array $attributes): array => ['trigger' => BackupTrigger::Manual]);
    }
}
