<!-- Integrations Section -->
@php
    // Connectors only — tools the agent can CALL (MCP servers + custom functions).
    // Messaging channels (where the agent gets DEPLOYED) live in their own partial
    // (landing.partials.channels), rendered below the features bar. Order controls
    // which marquee row each chip lands in.
    $wall = [
        ['img' => 'images/logos/connectors/slack.svg',           'label' => 'Slack'],
        ['img' => 'images/logos/connectors/hubspot.svg',         'label' => 'HubSpot'],
        ['img' => 'images/logos/connectors/salesforce.svg',      'label' => 'Salesforce'],
        ['img' => 'images/logos/connectors/notion.svg',          'label' => 'Notion'],
        ['img' => 'images/logos/connectors/github.svg',          'label' => 'GitHub'],
        ['img' => 'images/logos/connectors/zendesk.svg',         'label' => 'Zendesk'],
        ['img' => 'images/logos/connectors/linear.svg',          'label' => 'Linear'],
        ['img' => 'images/logos/connectors/atlassian.svg',       'label' => 'Atlassian'],
        ['img' => 'images/logos/connectors/stripe.svg',          'label' => 'Stripe'],
        ['img' => 'images/logos/connectors/gmail.svg',           'label' => 'Gmail'],
        ['img' => 'images/logos/connectors/google-calendar.svg', 'label' => 'Google Calendar'],
        ['img' => 'images/logos/connectors/google-drive.svg',    'label' => 'Google Drive'],
        ['img' => 'images/logos/connectors/outlook.svg',         'label' => 'Outlook'],
        ['img' => 'images/logos/connectors/calendly.svg',        'label' => 'Calendly'],
        ['img' => 'images/logos/connectors/asana.svg',           'label' => 'Asana'],
        ['img' => 'images/logos/connectors/todoist.svg',         'label' => 'Todoist'],
        ['img' => 'images/logos/connectors/box.svg',             'label' => 'Box'],
        ['img' => 'images/logos/connectors/amplitude.svg',       'label' => 'Amplitude'],
        ['img' => 'images/logos/connectors/sentry.svg',          'label' => 'Sentry'],
        ['img' => 'images/logos/connectors/fireflies.svg',       'label' => 'Fireflies'],
        ['img' => 'images/logos/connectors/perplexity.svg',      'label' => 'Perplexity'],
        ['img' => 'images/logos/connectors/zapier.svg',          'label' => 'Zapier'],
        ['img' => 'images/logos/connectors/weather.svg',         'label' => 'Weather'],
        ['img' => 'images/logos/connectors/currency-convertor.svg', 'label' => 'Currency Converter'],
        ['img' => 'images/logos/connectors/interactive-charts.svg', 'label' => 'Interactive charts'],
        ['img' => 'images/logos/connectors/leads.svg',           'label' => 'Leads'],
    ];

    // Split across two rows that scroll in opposite directions.
    $half = (int) ceil(count($wall) / 2);
    $rows = [array_slice($wall, 0, $half), array_slice($wall, $half)];
@endphp

<section id="integrations" class="relative overflow-hidden bg-gradient-to-b from-white via-indigo-50/30 to-white pt-16 sm:pt-24 pb-10">
    <!-- Soft decorative glows -->
    <div class="absolute inset-0 pointer-events-none">
        <div class="absolute top-1/3 left-1/4 w-72 h-72 bg-indigo-200/40 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 right-1/4 w-72 h-72 bg-purple-200/40 rounded-full blur-3xl"></div>
    </div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6">

        <!-- Heading -->
        <div class="text-center max-w-3xl mx-auto">
            <span class="block text-sm font-bold uppercase tracking-widest text-indigo-600 mb-4">
                {{ __('landing.integrations.eyebrow') }}
            </span>
            <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold text-slate-900 leading-tight tracking-tight">
                {{ __('landing.integrations.title') }}
                <span class="relative inline-block">
                    <span class="relative z-10">{{ __('landing.integrations.title_highlight') }}</span>
                    <span class="absolute bottom-1 left-0 right-0 h-3 sm:h-4 bg-linear-to-r from-indigo-200 via-indigo-300 to-purple-200 -rotate-1 opacity-60"></span>
                </span>
            </h2>
            <p class="mt-6 text-base sm:text-lg text-slate-600 leading-relaxed">
                {{ __('landing.integrations.subtitle') }}
            </p>
        </div>

        <!-- Logo marquee — pinned to LTR so the translateX animation + chip spacing
             stay identical on RTL (Arabic) pages; the logos are language-neutral. -->
        <div id="integrations-tools" dir="ltr" class="mt-12 sm:mt-16 scroll-mt-24">
            <div class="space-y-4 sm:space-y-5">
                @foreach ($rows as $r => $row)
                    <div class="int-marquee-group int-marquee-mask overflow-hidden">
                        <div class="int-marquee {{ $r % 2 === 0 ? 'int-marquee-track-left' : 'int-marquee-track-right' }}">
                            {{-- Render the row twice for a seamless loop; the copy is hidden from a11y. --}}
                            @foreach (array_merge($row, $row) as $i => $tool)
                                <div @if ($i >= count($row)) aria-hidden="true" @endif
                                     class="group/chip shrink-0 mr-3 sm:mr-4 inline-flex items-center gap-2.5 px-5 py-3 rounded-xl bg-white border border-slate-200/80 shadow-sm hover:border-indigo-200 hover:shadow-md transition-all duration-200">
                                    <img src="{{ asset($tool['img']) }}" alt="{{ $tool['label'] }}"
                                         class="w-6 h-6 sm:w-7 sm:h-7 object-contain grayscale opacity-70 group-hover/chip:grayscale-0 group-hover/chip:opacity-100 transition duration-200" />
                                    <span class="whitespace-nowrap text-sm font-medium text-slate-600 group-hover/chip:text-slate-900">{{ $tool['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>

    <style>
        /* Logo marquee */
        @keyframes int-marquee-left  { from { transform: translateX(0);     } to { transform: translateX(-50%); } }
        @keyframes int-marquee-right { from { transform: translateX(-50%);  } to { transform: translateX(0);    } }
        .int-marquee {
            display: flex;
            width: max-content;
            will-change: transform;
        }
        .int-marquee-track-left  { animation: int-marquee-left  45s linear infinite; }
        .int-marquee-track-right { animation: int-marquee-right 55s linear infinite; }
        .int-marquee-group:hover .int-marquee { animation-play-state: paused; }
        .int-marquee-mask {
            -webkit-mask-image: linear-gradient(to right, transparent 0, #000 6%, #000 94%, transparent 100%);
                    mask-image: linear-gradient(to right, transparent 0, #000 6%, #000 94%, transparent 100%);
        }

        @media (prefers-reduced-motion: reduce) {
            .int-marquee { animation: none; transform: none; }
        }
    </style>
</section>
