<?php

use Domain\Agent\Models\Agent;
use Domain\Llm\Enums\AiProvider;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * The widget's settings tell it which voice features to show: the dictation
 * mic only while speech to text is set up, the voice button only while voice
 * calls are.
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
