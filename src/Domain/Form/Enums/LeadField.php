<?php

declare(strict_types=1);

namespace Domain\Form\Enums;

/**
 * The standard lead fields a form field may map to through `lead_field_map`;
 * FormSubmission exposes each as an accessor over the submitted values.
 */
enum LeadField: string
{
    case Name = 'name';
    case Email = 'email';
    case Phone = 'phone';
    case JobTitle = 'job_title';
    case Company = 'company';
    case Location = 'location';
    case Message = 'message';
}
