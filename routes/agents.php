<?php

use App\Http\Controllers\Agents\AgentAppearanceController;
use App\Http\Controllers\Agents\AgentCreateController;
use App\Http\Controllers\Agents\AgentsController;
use App\Http\Controllers\Agents\AgentsIndexController;
use App\Http\Controllers\Agents\AgentStoreController;
use App\Http\Controllers\Agents\AgentTeamController;
use App\Http\Controllers\Agents\AgentUpdateActivityController;
use App\Http\Controllers\Agents\Files\AgentKnowledgeController;
use App\Http\Controllers\Agents\History\AgentHistoryController;
use App\Http\Controllers\Agents\Knowledge\AgentKnowledgeFileDeleteController;
use App\Http\Controllers\Agents\Knowledge\AgentKnowledgeFilesController;
use App\Http\Controllers\Agents\Knowledge\AgentKnowledgeFilesProcessController;
use App\Http\Controllers\Agents\Knowledge\AgentKnowledgeFileUploadController;
use App\Http\Controllers\Agents\Knowledge\AgentKnowledgeUploadController;
use App\Http\Controllers\Agents\Knowledge\AgentKnowledgeUploadedFilesDeleteController;
use App\Http\Controllers\Agents\Settings\AgentShowSettingsController;
use App\Http\Controllers\Agents\Settings\AgentUpdateSettingsController;
use App\Http\Controllers\Agents\ShowModelsController;
use App\Http\Controllers\Agents\Starters\StarterDeleteController;
use App\Http\Controllers\Agents\Starters\StartersShowController;
use App\Http\Controllers\Agents\Starters\StartersStoreController;
use App\Http\Controllers\Agents\Stats\AgentStatsController;
use App\Http\Controllers\GenerateRandomSubDomainController;
use App\Http\Controllers\ListModelsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('agents', AgentsController::class);
    Route::get('agents', AgentsIndexController::class)->name('agents.index');

    Route::post('agents', AgentStoreController::class)
        ->name('agents.store');

    Route::get('agents/create', AgentCreateController::class)->name('agents.create');

    Route::prefix('agents/{agent}')->name('agents.')->group(function () {
        Route::get('stats', AgentStatsController::class)->name('stats');

        Route::get('settings', AgentShowSettingsController::class)->name('settings');

        // Knowledge
        Route::get('knowledge', AgentKnowledgeController::class)->name('knowledge');
        Route::post('knowledge/upload', AgentKnowledgeUploadController::class)->name('knowledge.upload');
        Route::get('knowledge/files', AgentKnowledgeFilesController::class)->name('knowledge.files');
        Route::post('knowledge/files/upload', AgentKnowledgeFileUploadController::class)->name('knowledge.files.upload');
        Route::delete('knowledge/files/{file}', AgentKnowledgeFileDeleteController::class)->name('knowledge.files.delete');
        Route::delete('knowledge/uploaded-files', AgentKnowledgeUploadedFilesDeleteController::class)->name('knowledge.uploaded-files');
        Route::post('knowledge/files/process', AgentKnowledgeFilesProcessController::class)->name('knowledge.files.process');

        // Starters
        Route::get('starters', StartersShowController::class)->name('starters');
        Route::post('starters', StartersStoreController::class)->name('starters.store');
        Route::delete('starters/{id}', StarterDeleteController::class)->name('starters.delete');

        Route::get('history', AgentHistoryController::class)->name('history');

        Route::get('appearance', AgentAppearanceController::class)->name('appearance');

        Route::get('team', AgentTeamController::class)->name('team');

        Route::patch('activity', AgentUpdateActivityController::class)->name('activity');

        Route::patch('settings/{group}', AgentUpdateSettingsController::class)->name('settings.update');

        Route::get('/models/list', ListModelsController::class)->name('models.list');
        Route::get('/models', ShowModelsController::class)->name('models');

    });

    Route::get('generate-random-subdomain', GenerateRandomSubDomainController::class)->name('generate-random-subdomain');
});
