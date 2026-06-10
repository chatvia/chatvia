<!-- Pricing -->
@if ($pricing && isset($packages))
    <section class="relative bg-white" id="pricing">
        <div class="relative max-w-7xl mx-auto px-6 lg:px-8">
            <div class="py-12 md:py-20">

                <!-- Section header -->
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="h2 font-display text-slate-900">
                        Pricing
                    </h2>
                </div>

                @php
                    // Tier geometry for the desktop comparison table.
                    $tierCount = count($packages);
                    $colCount = $tierCount + 1;            // label column + one per tier
                    $colPct = 100 / $colCount;

                    // 0-based position of the popular tier among the packages (drives the
                    // highlighted-column background overlay below).
                    $popIndex = null;
                    $i = 0;
                    foreach ($packages as $p) {
                        if ($p['is_popular'] ?? false) { $popIndex = $i; break; }
                        $i++;
                    }
                    $popMarginPct = $popIndex !== null ? ($popIndex + 1) * $colPct : null;

                    // Cell helpers.
                    $fmt = fn ($n): string => number_format((int) $n);
                    $cap = fn (array $p, string $k): bool => (bool) ($p['capabilities'][$k] ?? false);
                    $has = fn (array $p, string $k): bool => array_key_exists($k, $p['features_monthly'] ?? []);
                    $isCs = fn (array $p): bool => (bool) ($p['contact_sales'] ?? false);

                    // Comparison rows, grouped. A row resolves to a number/string (shown as
                    // text) or a bool (check / dash). Derived from quota_limits + the plan
                    // capabilities DTO so the table can never drift from what the plan grants.
                    // A row may also carry `monthly`/`yearly` closures (toggle-aware value) and
                    // a `tooltip` (explanatory "?" next to the label).
                    $groups = [
                        'Usage' => [
                            [
                                'label' => 'Messages / month',
                                'value' => fn ($p) => $isCs($p) ? 'Custom' : $fmt($p['quota_limits']['monthly']['messages_limit'] ?? 0),
                                // When yearly is selected the plan grants an annual allowance —
                                // divide by 12 to show the equivalent monthly figure.
                                'monthly' => fn ($p) => $fmt($p['quota_limits']['monthly']['messages_limit'] ?? 0),
                                'yearly' => fn ($p) => $fmt((int) round(($p['quota_limits']['yearly']['messages_limit'] ?? 0) / 12)),
                            ],
                            ['label' => 'AI agents', 'value' => fn ($p) => $isCs($p) ? 'Unlimited' : $fmt($p['quota_limits']['fixed']['agents_limit'] ?? 0)],
                            ['label' => 'Knowledge pages', 'value' => fn ($p) => $isCs($p) ? 'Custom' : $fmt($p['quota_limits']['fixed']['pages_limit'] ?? 0)],
                            ['label' => 'Website links', 'value' => fn ($p) => $isCs($p) ? 'Custom' : $fmt($p['quota_limits']['fixed']['links_limit'] ?? 0)],
                            ['label' => 'Audio / video minutes', 'value' => fn ($p) => $isCs($p) ? 'Custom' : $fmt($p['quota_limits']['fixed']['minutes_limit'] ?? 0)],
                            ['label' => 'Team members', 'value' => fn ($p) => $isCs($p) ? 'Unlimited' : $fmt($p['quota_limits']['fixed']['workspace_users_limit'] ?? 0)],
                            ['label' => 'Teams', 'value' => fn ($p) => $isCs($p) ? 'Unlimited' : $fmt($p['quota_limits']['fixed']['teams_limit'] ?? 0)],
                            ['label' => 'Chat history', 'value' => fn ($p) => $isCs($p) ? 'Custom' : (($p['capabilities']['retentionDays'] ?? 0) . ' days'), 'tooltip' => 'How long each conversation is stored before it is automatically deleted.'],
                        ],
                        'Features' => [
                            ['label' => 'Sentiment analysis', 'value' => fn ($p) => $cap($p, 'sentiment'), 'tooltip' => 'Automatically detects whether each conversation feels positive, neutral, or negative.'],
                            ['label' => 'Tone analysis', 'value' => fn ($p) => $cap($p, 'tone'), 'tooltip' => 'Reads the emotional tone of a chat so your replies can stay on-brand.'],
                            ['label' => 'Topic analysis', 'value' => fn ($p) => $cap($p, 'topics'), 'tooltip' => 'Groups conversations by subject so you can see what people ask about most.'],
                            ['label' => 'Conversation Pulse', 'value' => fn ($p) => $cap($p, 'pulse'), 'tooltip' => 'A live health score for each conversation that blends sentiment and tone to flag chats that need attention.'],
                            ['label' => 'Handoff to humans', 'value' => fn ($p) => $cap($p, 'handoff'), 'tooltip' => 'Let a human agent step in and take over from the AI during a live chat.'],
                            ['label' => 'API access', 'value' => fn ($p) => $cap($p, 'api'), 'tooltip' => 'Connect Chatvia to your own apps and workflows through our API.'],
                            ['label' => 'MCP', 'value' => fn ($p) => $has($p, 'mcp'), 'tooltip' => 'Connect external tools and data to your agent using the Model Context Protocol.'],
                            ['label' => 'Function calling', 'value' => fn ($p) => $has($p, 'function_calling'), 'tooltip' => 'Let your agent call your own functions and webhooks to take actions during a chat.'],
                            ['label' => 'Custom integrations', 'value' => fn ($p) => $has($p, 'integrations'), 'tooltip' => 'Hook up your own tools and services to extend what your agents can do.'],
                            ['label' => 'White-label (remove branding)', 'value' => fn ($p) => $cap($p, 'removeBranding'), 'tooltip' => 'Hide the "Powered by Chatvia" badge across your widget and chat pages.'],
                        ],
                        'Security & support' => [
                            ['label' => 'SSO / SAML', 'value' => fn ($p) => $has($p, 'sso'), 'tooltip' => 'Let your team sign in through your company identity provider, like Okta or Azure AD.'],
                            ['label' => 'Uptime SLA', 'value' => fn ($p) => $has($p, 'sla'), 'tooltip' => 'A written guarantee on how often the service stays available.'],
                            ['label' => 'Dedicated support', 'value' => fn ($p) => $has($p, 'support'), 'tooltip' => 'A named contact and faster response times for your team.'],
                            ['label' => 'Custom contract & DPA', 'value' => fn ($p) => $has($p, 'dpa'), 'tooltip' => 'A tailored agreement and data-processing terms for your legal team.'],
                        ],
                    ];

                    // Heroicon paths reused for the included / not-included cells.
                    $checkPath = 'M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z';
                    $minusPath = 'M4 10a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H4.75A.75.75 0 0 1 4 10Z';
                @endphp

                <div x-data="{ annual: true }">

                    <!-- Billing toggle -->
                    <div class="flex justify-center items-center space-x-4 sm:space-x-7 mt-10 mb-4">
                        <div class="text-sm text-slate-500 font-medium text-right min-w-[8rem]">Pay Monthly</div>
                        <div class="form-switch shrink-0">
                            <input type="checkbox" id="toggle" class="sr-only" x-model="annual" />
                            <label class="bg-slate-300" for="toggle">
                                <span aria-hidden="true"></span>
                                <span class="sr-only">Pay annually</span>
                            </label>
                        </div>
                        <div class="text-sm text-slate-500 font-medium min-w-[8rem]">Pay Yearly
                            <span class="text-emerald-500">(-{{ $billingConfig['ui']['yearly_savings_percentage'] ?? 20 }}%)</span>
                        </div>
                    </div>

                    <!-- xs to lg: stacked cards -->
                    <div class="mx-auto mt-12 max-w-md space-y-8 lg:hidden">
                        @foreach ($packages as $packageKey => $package)
                            @php $pop = $package['is_popular'] ?? false; @endphp
                            <section class="p-8 {{ $pop ? 'rounded-2xl bg-slate-50 ring-1 ring-inset ring-slate-200' : '' }}">
                                <h3 class="text-sm font-semibold text-slate-900">{{ $package['name'] }}</h3>

                                <p class="mt-2 flex items-baseline gap-x-1 text-slate-900">
                                    @if ($isCs($package))
                                        <span class="text-4xl font-semibold">Custom</span>
                                    @else
                                        <span class="text-4xl font-semibold">{{ $billingConfig['currencySymbol'] }}<span
                                                x-text="annual ? '{{ number_format($package['yearly_price'] / 12 / 100) }}' : '{{ number_format($package['monthly_price'] / 100) }}'"></span></span>
                                        <span class="text-sm font-semibold text-slate-500">/month</span>
                                    @endif
                                </p>
                                <div class="mt-1 h-5 text-xs text-slate-500">
                                    @if ($isCs($package))
                                        Let's talk
                                    @else
                                        <span x-show="annual">Billed yearly at {{ $billingConfig['currencySymbol'] }}{{ number_format($package['yearly_price'] / 100, 0) }}</span>
                                    @endif
                                </div>

                                @if ($isCs($package))
                                    <a href="{{ route('contact') }}"
                                        class="mt-8 block w-full rounded-md bg-blue-600 px-3 py-2 text-center text-sm font-semibold text-white hover:bg-blue-700">
                                        Contact sales
                                    </a>
                                @else
                                    <form method="POST" action="{{ route('trial.checkout') }}" class="trial-form">
                                        @csrf
                                        <input type="hidden" name="package" value="{{ $packageKey }}">
                                        <input type="hidden" name="yearly" class="yearly-input" value="0">
                                        <button type="submit"
                                            class="mt-8 block w-full rounded-md px-3 py-2 text-center text-sm font-semibold {{ $pop ? 'bg-blue-600 text-white hover:bg-blue-700' : 'text-blue-600 ring-1 ring-inset ring-blue-200 hover:ring-blue-300' }}"
                                            onclick="this.form.querySelector('.yearly-input').value = document.getElementById('toggle').checked ? '1' : '0'">
                                            Start free trial
                                        </button>
                                    </form>
                                @endif

                                <ul role="list" class="mt-8 space-y-3 text-sm text-slate-700">
                                    @foreach ($groups as $rows)
                                        @foreach ($rows as $row)
                                            @php $value = ($row['value'])($package); @endphp
                                            @if (! is_bool($value) || $value)
                                                <li class="flex gap-x-3">
                                                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="h-6 w-5 flex-none text-emerald-500">
                                                        <path fill-rule="evenodd" clip-rule="evenodd" d="{{ $checkPath }}" />
                                                    </svg>
                                                    <span>{{ $row['label'] }}@if (isset($row['monthly']) && ! $isCs($package)) <span class="text-slate-500">(<span x-text="annual ? '{{ $row['yearly']($package) }}' : '{{ $row['monthly']($package) }}'"></span>)</span>@elseif (! is_bool($value)) <span class="text-slate-500">({{ $value }})</span>@endif</span>
                                                </li>
                                            @endif
                                        @endforeach
                                    @endforeach
                                </ul>
                            </section>
                        @endforeach
                    </div>

                    <!-- lg+: comparison table -->
                    <div class="isolate mt-16 hidden lg:block">
                        <div class="relative -mx-8">
                            @if ($popIndex !== null)
                                <div class="absolute inset-x-4 inset-y-0 -z-10 flex">
                                    <div class="flex px-4" style="margin-left: {{ $popMarginPct }}%; width: {{ $colPct }}%;" aria-hidden="true">
                                        <div class="w-full rounded-t-xl border-x border-t border-blue-200 bg-blue-50/60"></div>
                                    </div>
                                </div>
                            @endif

                            <table class="w-full table-fixed border-separate border-spacing-x-8 text-left">
                                <caption class="sr-only">Pricing plan comparison</caption>
                                <colgroup>
                                    <col style="width: {{ $colPct }}%" />
                                    @foreach ($packages as $package)
                                        <col style="width: {{ $colPct }}%" />
                                    @endforeach
                                </colgroup>

                                <thead>
                                    <tr>
                                        <td></td>
                                        @foreach ($packages as $package)
                                            <th scope="col" class="px-6 pt-6 xl:px-8 xl:pt-8">
                                                <span class="text-sm font-semibold text-slate-900">{{ $package['name'] }}</span>
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>

                                <tbody>
                                    <!-- Price + CTA -->
                                    <tr>
                                        <th scope="row"><span class="sr-only">Price</span></th>
                                        @foreach ($packages as $packageKey => $package)
                                            @php $pop = $package['is_popular'] ?? false; @endphp
                                            <td class="px-6 pt-2 xl:px-8">
                                                @if ($isCs($package))
                                                    <div class="flex items-baseline gap-x-1 text-slate-900">
                                                        <span class="text-4xl font-semibold">Custom</span>
                                                    </div>
                                                    <div class="mt-1 h-5 text-xs text-slate-500">Let's talk</div>
                                                    <a href="{{ route('contact') }}"
                                                        class="mt-8 block w-full rounded-md bg-blue-600 px-3 py-2 text-center text-sm font-semibold text-white hover:bg-blue-700">
                                                        Contact sales
                                                    </a>
                                                @else
                                                    <div class="flex items-baseline gap-x-1 text-slate-900">
                                                        <span class="text-4xl font-semibold">{{ $billingConfig['currencySymbol'] }}<span
                                                                x-text="annual ? '{{ number_format($package['yearly_price'] / 12 / 100) }}' : '{{ number_format($package['monthly_price'] / 100) }}'"></span></span>
                                                        <span class="text-sm font-semibold text-slate-500">/month</span>
                                                    </div>
                                                    <div class="mt-1 h-5 text-xs text-slate-500">
                                                        <span x-show="annual">Billed yearly at {{ $billingConfig['currencySymbol'] }}{{ number_format($package['yearly_price'] / 100, 0) }}</span>
                                                    </div>
                                                    <form method="POST" action="{{ route('trial.checkout') }}" class="trial-form">
                                                        @csrf
                                                        <input type="hidden" name="package" value="{{ $packageKey }}">
                                                        <input type="hidden" name="yearly" class="yearly-input" value="0">
                                                        <button type="submit"
                                                            class="mt-8 block w-full rounded-md px-3 py-2 text-center text-sm font-semibold {{ $pop ? 'bg-blue-600 text-white hover:bg-blue-700' : 'text-blue-600 ring-1 ring-inset ring-blue-200 hover:ring-blue-300' }}"
                                                            onclick="this.form.querySelector('.yearly-input').value = document.getElementById('toggle').checked ? '1' : '0'">
                                                            Start free trial
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>

                                    @foreach ($groups as $groupName => $rows)
                                        <tr>
                                            <th scope="colgroup" colspan="{{ $colCount }}"
                                                class="pb-4 {{ $loop->first ? 'pt-8' : 'pt-16' }} text-sm font-semibold text-slate-900">
                                                {{ $groupName }}
                                                <div class="absolute inset-x-8 mt-4 h-px bg-slate-900/10"></div>
                                            </th>
                                        </tr>
                                        @foreach ($rows as $row)
                                            <tr>
                                                <th scope="row" class="py-4 text-sm font-normal text-slate-700">
                                                    <span class="inline-flex items-center gap-x-1.5">
                                                        <span>{{ $row['label'] }}</span>
                                                        @if (! empty($row['tooltip']))
                                                            <span class="group relative inline-flex">
                                                                <span tabindex="0" role="button" aria-label="What is {{ $row['label'] }}?"
                                                                    class="flex size-4 cursor-help items-center justify-center rounded-full bg-slate-200 text-[10px] font-semibold text-slate-500 hover:bg-slate-300">?</span>
                                                                <span role="tooltip"
                                                                    class="pointer-events-none absolute bottom-full left-0 z-20 mb-2 w-64 rounded-lg bg-slate-900 px-3 py-2 text-xs font-normal leading-snug text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100">
                                                                    {{ $row['tooltip'] }}
                                                                </span>
                                                            </span>
                                                        @endif
                                                    </span>
                                                    <div class="absolute inset-x-8 mt-4 h-px bg-slate-900/5"></div>
                                                </th>
                                                @foreach ($packages as $package)
                                                    @php $value = ($row['value'])($package); @endphp
                                                    <td class="px-6 py-4 xl:px-8">
                                                        @if (isset($row['monthly']) && ! $isCs($package))
                                                            <div class="text-center text-sm text-slate-600">
                                                                <span x-text="annual ? '{{ $row['yearly']($package) }}' : '{{ $row['monthly']($package) }}'"></span>
                                                            </div>
                                                        @elseif (is_bool($value))
                                                            @if ($value)
                                                                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="mx-auto size-5 text-emerald-500">
                                                                    <path fill-rule="evenodd" clip-rule="evenodd" d="{{ $checkPath }}" />
                                                                </svg>
                                                                <span class="sr-only">Included in {{ $package['name'] }}</span>
                                                            @else
                                                                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="mx-auto size-5 text-slate-300">
                                                                    <path fill-rule="evenodd" clip-rule="evenodd" d="{{ $minusPath }}" />
                                                                </svg>
                                                                <span class="sr-only">Not included in {{ $package['name'] }}</span>
                                                            @endif
                                                        @else
                                                            <div class="text-center text-sm text-slate-600">{{ $value }}</div>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </section>
@endif
