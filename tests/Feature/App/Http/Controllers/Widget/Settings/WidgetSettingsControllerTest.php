<?php

use Domain\Agent\Models\Agent;
use Domain\Llm\Enums\AiProvider;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * The widget's settings tell it which voice features to show: the dictation
 * mic only while speech to text is set up, the voice button only while voice
 * calls are; which models the chatter may pick; and what removing a chat does.
 */
beforeEach(function (): void {
    connectAiProviders(AiProvider::OpenAi);
    config(['chatvia.ai.voice' => 'auto']);

    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->agent->setSetting('allow_voice_conversation', true, 'appearance');
    EmbeddedWidget::allowSite($this->agent);
});

it('tells the widget which voice features to show', function (array $config, string $key, bool $shown): void {
    config($config);

    $this->withHeaders(EmbeddedWidget::headers($this->agent))
        ->getJson('/widget/agents/settings')
        ->assertOk()
        ->assertJsonPath($key, $shown);
})->with([
    'dictation, no speech to text' => [['chatvia.ai.transcriber' => 'none'], 'allow_voice_input', false],
    'dictation, speech to text set up' => [['chatvia.ai.transcriber' => 'openai'], 'allow_voice_input', true],
    'voice button, voice calls set up' => [[], 'voice_conversation', true],
    'voice button, voice calls not set up' => [['chatvia.ai.voice' => 'none'], 'voice_conversation', false],
]);

it('lists the models the chatter may pick, the agent\'s own first', function (): void {
    $own = testLlm('own-model');
    $other = testLlm('other-model');
    $this->agent->forceFill(['llm_id' => $own->id])->saveQuietly();
    config(['chatvia.ai.chatter_model_choice' => true]);
    $this->agent->setSetting('enabled', true, 'model_choice');
    $this->agent->setSetting('models', [$other->id], 'model_choice');

    $this->withHeaders(EmbeddedWidget::headers($this->agent))
        ->getJson('/widget/agents/settings')
        ->assertOk()
        ->assertJsonPath('models.0.id', $own->id)
        ->assertJsonPath('models.0.is_default', true)
        ->assertJsonPath('models.1.id', $other->id);
});

it('tells the widget what removing a chat does, ending it by default', function (?string $stored, string $sent): void {
    config(['chatvia.widget.chat_deletion_mode' => $stored]);

    $this->withHeaders(EmbeddedWidget::headers($this->agent))
        ->getJson('/widget/agents/settings')
        ->assertOk()
        ->assertJsonPath('chat_deletion_mode', $sent);
})->with([
    'not set' => [null, 'end'],
    'hide' => ['hide', 'hide'],
    'delete' => ['delete', 'delete'],
    'an unknown value' => ['archive', 'end'],
]);
