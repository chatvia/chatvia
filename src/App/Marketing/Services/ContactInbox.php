<?php

namespace App\Marketing\Services;

use Illuminate\Container\Attributes\Config;

/**
 * Where contact-form messages go: the `chatvia.contact_email` address. When it
 * is not set the install has no inbox, and the contact form is turned off.
 */
readonly class ContactInbox
{
    public function __construct(
        #[Config('chatvia.contact_email')]
        private mixed $address = null,
    ) {}

    public function address(): ?string
    {
        return is_string($this->address) && $this->address !== '' ? $this->address : null;
    }

    public function isOpen(): bool
    {
        return $this->address() !== null;
    }
}
