<?php

namespace Domain\Form\Enums;

/**
 * What a form collects. The type does not change how a submission is stored or
 * delivered; it labels the form for the owner and in the CRM payload
 * (`form_type`).
 */
enum FormType: string
{
    case Lead = 'lead';
    case Booking = 'booking';
    case Qualification = 'qualification';
    case Generic = 'generic';
}
