<?php

namespace Domain\Form\Enums;

enum FormType: string
{
    case Lead = 'lead';
    case Booking = 'booking';
    case Qualification = 'qualification';
    case Generic = 'generic';

    /**
     * Lead-bound forms funnel their mapped fields into the encrypted `leads`
     * table (and fire FormSubmitted → CRM webhook). Generic/booking forms only
     * store a FormSubmission.
     */
    public function isLeadBound(): bool
    {
        return $this === self::Lead || $this === self::Qualification;
    }
}
