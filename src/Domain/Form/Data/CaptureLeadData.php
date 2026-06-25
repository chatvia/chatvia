<?php

namespace Domain\Form\Data;

use Spatie\LaravelData\Data;

/**
 * Input for the conversational `capture-lead` tool. Carries the lead field
 * values the agent gathered in chat; recorded as a submission under the agent's
 * default lead form. `source` distinguishes the capture origin ('tool').
 */
class CaptureLeadData extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $job_title = null,
        public ?string $company = null,
        public ?string $location = null,
        public ?string $message = null,
        public ?string $ip = null,
        public ?string $userAgent = null,
        public string $source = 'tool',
    ) {}

    /**
     * Non-null field values keyed by column.
     *
     * @return array<string, string>
     */
    public function values(): array
    {
        return array_filter([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'company' => $this->company,
            'location' => $this->location,
            'message' => $this->message,
        ], static fn ($v): bool => $v !== null && $v !== '');
    }
}
