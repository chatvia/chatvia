<?php

use Domain\Agent\Models\Agent;
use Domain\Llm\Enums\AiProvider;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The hosted chat page gets from the server which composer features to show,
 * so it never offers what the server would refuse: uploads only with a model
 * that reads PDFs, voice calls and dictation only while they are set up and
 * on for the agent, the visitor language switcher with the owner's list, and
 * the models the chatter may pick while Multi-model is on, and whether a
 * thumbs-down asks what was wrong.
 */
beforeEach(function (): void {
    withLicense();

    $this->agent = Agent::factory()->create(['is_public' => true]);
});

it('tells the chat page which composer features to show', function (Closure $arrange, string $prop, mixed $expected): void {
    $arrange($this->agent);

    $this->get('/chat/'.$this->agent->slug)
        ->assertInertia(fn (Assert $page): Assert => $page->component('chat/Home')->where($prop, $expected));
})->with([
    'uploads, allowed with a model that reads files' => [function (Agent $agent): void {
        $agent->setSetting('allow_file_uploads', true, 'appearance');
        useChatFiles();
    }, 'allowFileUploads', true],
    'uploads, the types that model reads' => [function (Agent $agent): void {
        useChatFiles();
    }, 'uploadAccept', 'application/pdf,image/png,image/jpeg,image/webp,image/gif'],
    'uploads, allowed without a model that reads files' => [function (Agent $agent): void {
        $agent->setSetting('allow_file_uploads', true, 'appearance');
    }, 'allowFileUploads', false],
    'uploads, turned off by an admin' => [function (Agent $agent): void {
        $agent->setSetting('allow_file_uploads', true, 'appearance');
        useChatFiles();
        useChatFiles(false);
    }, 'allowFileUploads', false],
    'uploads, turned off by the owner' => [function (Agent $agent): void {
        $agent->setSetting('allow_file_uploads', false, 'appearance');
        useChatFiles();
    }, 'allowFileUploads', false],
    'voice calls, on for the agent' => [function (Agent $agent): void {
        connectAiProviders(AiProvider::OpenAi);
        config(['chatvia.ai.voice' => 'auto']);
        $agent->setSetting('allow_voice_conversation', true, 'appearance');
    }, 'voiceConversation', true],
    'voice calls, off for the agent' => [function (Agent $agent): void {
        connectAiProviders(AiProvider::OpenAi);
        config(['chatvia.ai.voice' => 'auto']);
        $agent->setSetting('allow_voice_conversation', false, 'appearance');
    }, 'voiceConversation', false],
    'dictation, no speech to text set up' => [function (): void {
        config(['chatvia.ai.transcriber' => 'none']);
    }, 'allowVoiceInput', false],
    'dictation, speech to text set up' => [function (): void {
        connectAiProviders(AiProvider::OpenAi);
        config(['chatvia.ai.transcriber' => 'openai']);
    }, 'allowVoiceInput', true],
    'dictation, off for the agent' => [function (Agent $agent): void {
        connectAiProviders(AiProvider::OpenAi);
        config(['chatvia.ai.transcriber' => 'openai']);
        $agent->setSetting('allow_voice_input', false, 'appearance');
    }, 'allowVoiceInput', false],
    'feedback note, by default' => [function (): void {}, 'feedbackComment', true],
    'feedback note, off for the agent' => [function (Agent $agent): void {
        $agent->setSetting('allow_feedback_comment', false, 'appearance');
    }, 'feedbackComment', false],
    'language switcher, by default' => [function (): void {}, 'enableLanguageSwitcher', true],
    'language switcher, hidden by the owner' => [function (Agent $agent): void {
        $agent->setSetting('show_language_switcher', false, 'embed');
    }, 'enableLanguageSwitcher', false],
    'languages, none picked: every language but "auto"' => [function (): void {
        config(['chatvia.languages' => ['auto' => 'Auto-detect', 'en' => 'English', 'de' => 'Deutsch', 'ar' => 'العربية']]);
    }, 'enabledLanguages', ['en', 'de', 'ar']],
    'languages, picked by the owner' => [function (Agent $agent): void {
        $agent->setSetting('enabled_languages', ['de', 'fr'], 'appearance');
    }, 'enabledLanguages', ['de', 'fr']],
]);

it('lists the models the chatter may pick only while Multi-model is on', function (bool $admin, int $count): void {
    $own = testLlm('own-model');
    $other = testLlm('other-model');
    $this->agent->forceFill(['llm_id' => $own->id])->saveQuietly();
    config(['chatvia.ai.chatter_model_choice' => $admin]);
    $this->agent->setSetting('enabled', true, 'model_choice');
    $this->agent->setSetting('models', [$other->id], 'model_choice');

    $this->get('/chat/'.$this->agent->slug)
        ->assertInertia(fn (Assert $page): Assert => $page->component('chat/Home')->has('models', $count));
})->with([
    'admin allows it' => [true, 2],
    'admin does not' => [false, 0],
]);
