<?php

use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Notifications\DataExportReadyNotification;

/**
 * The bell entry of a ready data export opens the data export page, or the
 * download itself while owners may not open that page (Settings → Workspaces
 * → Owner account).
 */
it('opens the data export page, or the download while that page is off', function (bool $pageOn, string $route): void {
    config(['chatvia.account.data_export' => $pageOn]);
    $user = User::factory()->create();
    $export = DataExport::factory()->ready()->create(['user_id' => $user->id]);

    $message = new DataExportReadyNotification($export)->toInbox($user);

    expect($message->content->url)->toBe(route($route, $route === 'account.data-export.download' ? $export : [], absolute: false));
})->with([
    'page on' => [true, 'account.data-export.edit'],
    'page off' => [false, 'account.data-export.download'],
]);
