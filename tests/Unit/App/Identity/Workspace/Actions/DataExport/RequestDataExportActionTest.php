<?php

use App\Identity\Workspace\Actions\DataExport\RequestDataExportAction;
use App\Identity\Workspace\Data\DataExport\RequestDataExportData;
use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Exceptions\ActiveDataExportExistsException;
use App\Identity\Workspace\Jobs\PrepareDataExportJob;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Queue;

/**
 * Requesting a data export queues one pending export at a time: a pending one,
 * or a ready one still in its download window, blocks a second request; an
 * expired or failed one does not.
 */
beforeEach(function (): void {
    Queue::fake();
    $this->user = User::factory()->create();
});

/** An earlier export of the user, in the given state. */
function rdeEarlierExport(User $user, string $state): DataExport
{
    $factory = DataExport::factory();

    $state = match ($state) {
        'pending' => $factory,
        'ready' => $factory->ready(),
        'expired' => $factory->ready()->state(['expires_at' => now()->subHour()]),
        'failed' => $factory->failed(),
    };

    return $state->create(['user_id' => $user->id]);
}

it('creates a pending export and queues its build once earlier exports are over', function (): void {
    rdeEarlierExport($this->user, 'expired');
    rdeEarlierExport($this->user, 'failed');

    $export = app(RequestDataExportAction::class)->execute(new RequestDataExportData(user: $this->user));

    expect($export->user_id)->toBe($this->user->id)
        ->and($export->status)->toBe(DataExportStatus::Pending)
        ->and($export->download_token)->toHaveLength(64);
    Queue::assertPushed(PrepareDataExportJob::class, fn (PrepareDataExportJob $job): bool => $job->dataExportId === $export->id);
});

it('refuses a second export while one is still active', function (string $state): void {
    rdeEarlierExport($this->user, $state);

    expect(fn () => app(RequestDataExportAction::class)->execute(new RequestDataExportData(user: $this->user)))
        ->toThrow(ActiveDataExportExistsException::class)
        ->and(DataExport::query()->where('user_id', $this->user->id)->count())->toBe(1);
    Queue::assertNothingPushed();
})->with([
    'pending' => 'pending',
    'ready and not expired' => 'ready',
]);
