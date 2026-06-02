<?php

namespace Domain\Agent\Data\Resources\ChatLog\Handoff;

use Domain\Lead\Models\Lead;
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

    public static function fromLead(Lead $lead): self
    {
        return new self(
            id: $lead->id,
            name: $lead->name,
            email: $lead->email,
            phone: $lead->phone,
            job_title: $lead->job_title,
            company: $lead->company,
            location: $lead->location,
            message: $lead->message,
            referrer_url: $lead->referrer_url,
            page_url: $lead->page_url,
            locale: $lead->locale,
            created_at: $lead->created_at->toISOString(),
        );
    }
}
