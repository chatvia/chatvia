<?php

use App\Http\Controllers\Billing\SubscriptionWebhook;
use App\Http\Controllers\ConsentController;
use App\Jobs\IngestionJob;
use App\Support\AzureDocumentIntelligence;
use App\Support\Subdomain;
use Illuminate\Support\Facades\Route;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Extension\CommonMark\Node\Inline\Text;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Node\NodeWalker;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Renderer\HtmlRenderer;

Route::get('/', function () {
    return view('landing.index');
})->name('home');

// Consent routes
Route::get('/consent', [ConsentController::class, 'show'])->name('consent.show');
Route::post('/consent', [ConsentController::class, 'store'])->name('consent.store');

Route::post('/webhooks/subscription', SubscriptionWebhook::class)->name('subscription.webhook');

Route::get('/test', function () {

    $agent = \App\Models\AgentFile::first();

    dd($agent->ingestionProgress());

    exit;
//    return app(\App\Support\Rag::class)->bm25($agent->id, 'animal');
    return app(\App\Support\Rag::class)->search($agent->id, 'what is Project Gutenberg?');


//    file_put_contents('1.json',
//        json_encode(app(AzureDocumentIntelligence::class)
//            ->getAnalysisResult($agent), JSON_PRETTY_PRINT)
//    );

   \App\Jobs\IngestionJob::dispatch($agent);

//    IngestionJob::dispatchSync(\App\Models\AgentFile::find(1));

//    $d = app(\App\Support\OpenRouter::class)->pdf(
//        file_get_contents(base_path('cv.pdf'))
//    );
//
//    dd($d);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/chat.php';
require __DIR__.'/dashboard.php';
require __DIR__.'/agents.php';
require __DIR__.'/billing.php';
require __DIR__.'/ingestion.php';
