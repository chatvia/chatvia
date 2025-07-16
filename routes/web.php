<?php

use App\Http\Controllers\Billing\SubscriptionWebhook;
use App\Http\Controllers\ConsentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing.index');
})->name('home');

// Consent routes
Route::get('/consent', [ConsentController::class, 'show'])->name('consent.show');
Route::post('/consent', [ConsentController::class, 'store'])->name('consent.store');

Route::post('/webhooks/subscription', SubscriptionWebhook::class)->name('subscription.webhook');

Route::get('/test', function () {
    $agent = \App\Models\Agent::first();
    //    $chunks = app(\App\Support\KnowledgeBase::class)->chunks(
    //        \App\Models\Agent::first(), 'What is this book about?'
    //    );
    //
    //    dd(app(\App\Support\Cohere::class)->rerank('What is this book about?', $chunks->toArray()));

    $memory = app(\App\Support\Chat\ChatMemory::class)
        ->setAgent($agent)
        ->setConversationId('260e07e2-3b01-4336-bd6e-0235a58804e6');

    dd($memory->getLlmMessages());

    dd(

        $memory->updateConversationTitle('Hello')
    );
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/chat.php';
require __DIR__.'/dashboard.php';
require __DIR__.'/agents.php';
require __DIR__.'/billing.php';
require __DIR__.'/ingestion.php';
