<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — Chatvia</title>
    {{-- Draft legal documents: keep out of search indexes until reviewed/published. --}}
    <meta name="robots" content="noindex">
    @vite(['resources/css/landing.css'])
    <style>
        .legal-prose {
            color: #1f2937;
            line-height: 1.7;
        }

        .legal-prose h2 {
            font-size: 1.4rem;
            font-weight: 700;
            margin: 2rem 0 .75rem;
            color: #0f172a;
        }

        .legal-prose h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 1.5rem 0 .5rem;
            color: #0f172a;
        }

        .legal-prose h4 {
            font-size: 1rem;
            font-weight: 600;
            margin: 1.25rem 0 .5rem;
            color: #0f172a;
        }

        .legal-prose p {
            margin: .75rem 0;
        }

        .legal-prose ul,
        .legal-prose ol {
            margin: .75rem 0 .75rem 1.5rem;
        }

        .legal-prose li {
            margin: .35rem 0;
        }

        .legal-prose a {
            color: #2563eb;
            text-decoration: underline;
        }

        .legal-prose strong {
            color: #0f172a;
        }

        .legal-prose hr {
            margin: 2rem 0;
            border: 0;
            border-top: 1px solid #e5e7eb;
        }

        .legal-prose blockquote {
            margin: 1rem 0;
            padding: .75rem 1rem;
            border-left: 3px solid #93c5fd;
            background: #eff6ff;
            color: #1e3a8a;
            border-radius: 0 .375rem .375rem 0;
        }

        .legal-prose table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            font-size: .9rem;
        }

        .legal-prose th,
        .legal-prose td {
            border: 1px solid #e5e7eb;
            padding: .5rem .75rem;
            text-align: left;
            vertical-align: top;
        }

        .legal-prose th {
            background: #f8fafc;
            font-weight: 600;
        }

        .legal-prose code {
            background: #f1f5f9;
            padding: .1rem .35rem;
            border-radius: .25rem;
            font-size: .85em;
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-900">
    <div class="min-h-screen">
        <header class="bg-white shadow-sm border-b">
            <div class="max-w-4xl mx-auto px-4">
                <div class="flex h-16 items-center justify-between">
                    <a href="{{ route('home') }}" class="text-2xl font-bold text-gray-900">Chatvia</a>
                    <a href="{{ route('home') }}"
                        class="text-sm font-medium text-slate-500 hover:text-blue-600">← Back to home</a>
                </div>
            </div>
        </header>

        <main class="max-w-4xl mx-auto px-4 py-12 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-sm border p-6 sm:p-10">
                <h1 class="text-3xl font-bold text-gray-900">{{ $title }}</h1>
                @if (! empty($effectiveDate))
                    <p class="mt-2 text-sm text-slate-500">Last updated: {{ $effectiveDate }}</p>
                @endif
                <div class="legal-prose mt-8">
                    {{-- Content is rendered from our own trusted, version-controlled markdown. --}}
                    {!! $html !!}
                </div>
            </div>
            @php
                $operator = array_filter([
                    \Support\Cms\Marketing\CompanyDetails::name() !== null
                        ? 'Operated by '.\Support\Cms\Marketing\CompanyDetails::name().(\Support\Cms\Marketing\CompanyDetails::vatId() !== null ? ' (VAT '.\Support\Cms\Marketing\CompanyDetails::vatId().')' : '')
                        : null,
                    \Support\Cms\Marketing\CompanyDetails::inlineAddress(),
                ]);
                $operatorEmail = \Support\Cms\Marketing\CompanyDetails::email();
            @endphp
            @if ($operator !== [] || $operatorEmail !== null)
                <p class="mt-6 text-center text-xs text-slate-400">
                    {{ implode(', ', $operator) }}
                    @if ($operatorEmail !== null)
                        @if ($operator !== []) · @endif
                        <a href="mailto:{{ $operatorEmail }}" class="hover:text-blue-600">{{ $operatorEmail }}</a>
                    @endif
                </p>
            @endif
        </main>
    </div>
</body>

</html>
