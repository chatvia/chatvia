<?php

namespace Domain\Agent\Data\Resources\ChatLog\Handoff;

use Domain\Form\Models\FormSubmission;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ChatLogLeadData extends Data
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $email,
        public ?string $phone,
        public ?string $job_title,
        public ?string $company,
        public ?string $location,
        public ?string $message,
        public ?string $referrer_url,
        public ?string $page_url,
        public ?string $locale,
        public string $created_at,
    ) {}

    public static function fromLead(FormSubmission $submission): self
    {
        return new self(
            id: $submission->id,
            name: $submission->name,
            email: $submission->email,
            phone: $submission->phone,
            job_title: $submission->job_title,
            company: $submission->company,
            location: $submission->location,
            message: $submission->message,
            referrer_url: null,
            page_url: null,
            locale: null,
            created_at: $submission->created_at->toISOString(),
        );
    }
}
