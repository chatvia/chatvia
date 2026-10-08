{{-- The operator's contact email from config('chatvia.company.email') as a
     mailto link, or plain fallback text when none is configured. --}}
@php($companyEmail = \Support\Cms\Marketing\CompanyDetails::email())
@if ($companyEmail !== null)<a href="mailto:{{ $companyEmail }}" class="{{ $class ?? 'underline' }}">{{ $companyEmail }}</a>@else{{ $fallback ?? 'our support team' }}@endif
