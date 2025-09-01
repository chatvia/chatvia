<?php

use App\Http\Controllers\Leads\LeadsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('leads', [LeadsController::class, 'index'])->name('leads.index');
    Route::patch('leads/{lead}/status', [LeadsController::class, 'updateStatus'])->name('leads.update-status');
});
