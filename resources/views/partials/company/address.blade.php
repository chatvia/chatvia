{{-- The operator's postal block (name, address lines, VAT id) from
     config('chatvia.company'). Renders nothing when none is configured.
     Pass `class` for the <address> element. --}}
@php
    $companyLines = array_values(array_filter([
        \App\Marketing\CompanyDetails::name(),
        ...\App\Marketing\CompanyDetails::addressLines(),
        \App\Marketing\CompanyDetails::vatId() !== null ? 'VAT '.\App\Marketing\CompanyDetails::vatId() : null,
    ]));
@endphp
@if ($companyLines !== [])
    <address class="{{ $class ?? '' }}">
        @foreach ($companyLines as $companyLine)
            {{ $companyLine }}@unless ($loop->last)<br>@endunless
        @endforeach
    </address>
@endif
