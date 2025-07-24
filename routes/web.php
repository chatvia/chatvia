<?php

use App\Http\Controllers\ConsentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing.index');
})->name('home');

// Consent routes
Route::get('/consent', [ConsentController::class, 'show'])->name('consent.show');
Route::post('/consent', [ConsentController::class, 'store'])->name('consent.store');

Route::get('/test', function () {
    $user = \App\Models\User::find(1);

    dd($user->usage);
});

require __DIR__.'/dashboard.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/chat.php';
require __DIR__.'/agents.php';
require __DIR__.'/billing.php';
require __DIR__.'/ingestion.php';
