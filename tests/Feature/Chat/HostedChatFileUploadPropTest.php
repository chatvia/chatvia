<?php

use Domain\Agent\Models\Agent;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Services\DocumentLlm;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Hosted chat uploads are PDFs, which only an LLM that reads PDFs can answer.
 * The page gets `allowFileUploads` from the server, so the composer hides its
 * upload button instead of offering an upload the server would refuse (422).
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->agent->update(['is_public' => true]);
    $this->agent->setSetting('allow_file_uploads', true, 'appearance');
    $this->host = $this->agent->sub_domain.'.chatvia.test';

});

it('offers uploads when the owner allows them and a PDF-reading LLM is set up', function (): void {
    app()->instance(DocumentLlm::class, new DocumentLlm(LlmProviderEnum::MISTRAL));

    $this->get('https://'.$this->host.'/chat')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('chat/Home')
            ->where('allowFileUploads', true));
});

it('hides uploads when no LLM can read PDF files', function (): void {
    app()->instance(DocumentLlm::class, new DocumentLlm(null));

    $this->get('https://'.$this->host.'/chat')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('chat/Home')
            ->where('allowFileUploads', false));
});

it('hides uploads when the owner turned them off', function (): void {
    app()->instance(DocumentLlm::class, new DocumentLlm(LlmProviderEnum::MISTRAL));
    $this->agent->setSetting('allow_file_uploads', false, 'appearance');

    $this->get('https://'.$this->host.'/chat')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('chat/Home')
            ->where('allowFileUploads', false));
});
