<?php

it('loads no support widget on the marketing site when no widget id is set', function (): void {
    config(['chatvia.widget.chatvia_widget_id' => null]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('chatbot-embed-script', false);
});

it('loads the support widget for the configured agent', function (): void {
    config([
        'chatvia.widget.chatvia_widget_id' => 'agent-123',
        'chatvia.widget.script_url_embed' => 'https://cdn.example.test/embed.js',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('id="chatbot-embed-script"', false)
        ->assertSee('src="https://cdn.example.test/embed.js"', false)
        ->assertSee('data-agent-id="agent-123"', false);
});
