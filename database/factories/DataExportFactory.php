<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A person's data export, waiting in the queue, unless a state moves it on.
 * A ready export points at a file path but writes no file.
 *
 * @extends Factory<DataExport>
 */
class DataExportFactory extends Factory
{
    #[\Override]
    protected $model = DataExport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => DataExportStatus::Pending,
            'download_token' => fn (): string => Str::random(64),
        ];
    }

    public function processing(): static
    {
        return $this->state(['status' => DataExportStatus::Processing]);
    }

    /** Built, with a file to download until tomorrow. */
    public function ready(string $filePath = 'exports/export.zip'): static
    {
        return $this->state(fn (): array => [
            'status' => DataExportStatus::Ready,
            'file_path' => $filePath,
            'file_size' => 1024,
            'expires_at' => now()->addDay(),
        ]);
    }

    public function failed(string $error = 'The export could not be built.'): static
    {
        return $this->state(['status' => DataExportStatus::Failed, 'error_message' => $error]);
    }

    /** Ready once, but its download time has passed. */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => DataExportStatus::Expired,
            'expires_at' => now()->subDay(),
        ]);
    }
}
