<!-- Pricing -->
@if ($pricing && isset($packages))
    <section class="relative bg-white" id="pricing">
        <div class="relative max-w-7xl mx-auto px-6 lg:px-8">
            <div class="py-12 md:py-20">

                <!-- Section header -->
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="h2 font-display text-slate-900">
                        {{ __('landing.pricing.heading') }}
                    </h2>
                    <p class="mt-3 inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50 px-4 py-1.5 text-xs sm:text-sm font-medium text-emerald-800">
                        {{ __('landing.pricing.residency_note') }}
                    </p>
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
                        __('landing.pricing.groups.usage') => [
                            [
                                'label' => __('landing.pricing.rows.messages'),
                                'value' => fn ($p) => $isCs($p) ? __('landing.pricing.custom') : $fmt($p['quota_limits']['monthly']['messages_limit'] ?? 0),
                                // When yearly is selected the plan grants an annual allowance —
                                // divide by 12 to show the equivalent monthly figure.
                                'monthly' => fn ($p) => $fmt($p['quota_limits']['monthly']['messages_limit'] ?? 0),
                                'yearly' => fn ($p) => $fmt((int) round(($p['quota_limits']['yearly']['messages_limit'] ?? 0) / 12)),
                            ],
                            ['label' => __('landing.pricing.rows.agents'), 'value' => fn ($p) => $isCs($p) ? __('landing.pricing.unlimited') : $fmt($p['quota_limits']['fixed']['agents_limit'] ?? 0)],
                            ['label' => __('landing.pricing.rows.pages'), 'value' => fn ($p) => $isCs($p) ? __('landing.pricing.custom') : $fmt($p['quota_limits']['fixed']['pages_limit'] ?? 0)],
                            ['label' => __('landing.pricing.rows.links'), 'value' => fn ($p) => $isCs($p) ? __('landing.pricing.custom') : $fmt($p['quota_limits']['fixed']['links_limit'] ?? 0)],
                            ['label' => __('landing.pricing.rows.minutes'), 'value' => fn ($p) => $isCs($p) ? __('landing.pricing.custom') : $fmt($p['quota_limits']['fixed']['minutes_limit'] ?? 0)],
                            ['label' => __('landing.pricing.rows.team_members'), 'value' => fn ($p) => $isCs($p) ? __('landing.pricing.unlimited') : $fmt($p['quota_limits']['fixed']['workspace_users_limit'] ?? 0)],
                            ['label' => __('landing.pricing.rows.teams'), 'value' => fn ($p) => $isCs($p) ? __('landing.pricing.unlimited') : $fmt($p['quota_limits']['fixed']['teams_limit'] ?? 0)],
                            ['label' => __('landing.pricing.rows.chat_history'), 'value' => fn ($p) => $isCs($p) ? __('landing.pricing.custom') : (($p['capabilities']['retentionDays'] ?? 0) . ' ' . __('landing.pricing.days')), 'tooltip' => __('landing.pricing.tips.chat_history')],
                        ],
                        __('landing.pricing.groups.features') => [
                            ['label' => __('landing.pricing.rows.sentiment'), 'value' => fn ($p) => $cap($p, 'sentiment'), 'tooltip' => __('landing.pricing.tips.sentiment')],
                            ['label' => __('landing.pricing.rows.tone'), 'value' => fn ($p) => $cap($p, 'tone'), 'tooltip' => __('landing.pricing.tips.tone')],
                            ['label' => __('landing.pricing.rows.topics'), 'value' => fn ($p) => $cap($p, 'topics'), 'tooltip' => __('landing.pricing.tips.topics')],
                            ['label' => __('landing.pricing.rows.pulse'), 'value' => fn ($p) => $cap($p, 'pulse'), 'tooltip' => __('landing.pricing.tips.pulse')],
                            ['label' => __('landing.pricing.rows.handoff'), 'value' => fn ($p) => $cap($p, 'handoff'), 'tooltip' => __('landing.pricing.tips.handoff')],
                            ['label' => __('landing.pricing.rows.api'), 'value' => fn ($p) => $cap($p, 'api'), 'tooltip' => __('landing.pricing.tips.api')],
                            ['label' => __('landing.pricing.rows.mcp'), 'value' => fn ($p) => $has($p, 'mcp'), 'tooltip' => __('landing.pricing.tips.mcp')],
                            ['label' => __('landing.pricing.rows.function_calling'), 'value' => fn ($p) => $has($p, 'function_calling'), 'tooltip' => __('landing.pricing.tips.function_calling')],
                            ['label' => __('landing.pricing.rows.integrations'), 'value' => fn ($p) => $has($p, 'integrations'), 'tooltip' => __('landing.pricing.tips.integrations')],
                            ['label' => __('landing.pricing.rows.white_label'), 'value' => fn ($p) => $cap($p, 'removeBranding'), 'tooltip' => __('landing.pricing.tips.white_label')],
                        ],
                        __('landing.pricing.groups.security_support') => [
                            ['label' => __('landing.pricing.rows.access_control'), 'value' => fn ($p) => $cap($p, 'security'), 'tooltip' => __('landing.pricing.tips.access_control')],
                            ['label' => __('landing.pricing.rows.siem'), 'value' => fn ($p) => $cap($p, 'siemWebhook'), 'tooltip' => __('landing.pricing.tips.siem')],
                            ['label' => __('landing.pricing.rows.sso'), 'value' => fn ($p) => $has($p, 'sso'), 'tooltip' => __('landing.pricing.tips.sso')],
                            ['label' => __('landing.pricing.rows.sla'), 'value' => fn ($p) => $has($p, 'sla'), 'tooltip' => __('landing.pricing.tips.sla')],
                            ['label' => __('landing.pricing.rows.support'), 'value' => fn ($p) => $has($p, 'support'), 'tooltip' => __('landing.pricing.tips.support')],
                            ['label' => __('landing.pricing.rows.dpa'), 'value' => fn ($p) => $has($p, 'dpa'), 'tooltip' => __('landing.pricing.tips.dpa')],
                        ],
                    ];

                    // Heroicon paths reused for the included / not-included cells.
                    $checkPath = 'M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z';
                    $minusPath = 'M4 10a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H4.75A.75.75 0 0 1 4 10Z';
                @endphp

                <div x-data="{ annual: true }">

                    <!-- Billing toggle -->
                    <div class="flex justify-center items-center space-x-4 sm:space-x-7 mt-10 mb-4">
                        <div class="text-sm text-slate-500 font-medium text-end min-w-[8rem]">{{ __('landing.pricing.pay_monthly') }}</div>
                        <div class="form-switch shrink-0">
                            <input type="checkbox" id="toggle" class="sr-only" x-model="annual" />
                            <label class="bg-slate-300" for="toggle">
                                <span aria-hidden="true"></span>
                                <span class="sr-only">{{ __('landing.pricing.pay_yearly') }}</span>
                            </label>
                        </div>
                        <div class="text-sm text-slate-500 font-medium min-w-[8rem]">{{ __('landing.pricing.pay_yearly') }}
                            <span class="text-emerald-500">(-{{ $billingConfig['ui']['yearly_savings_percentage'] ?? 20 }}%)</span>
                        </div>
                    </div>

                    <!-- xs to lg: stacked cards -->
                    <div class="mx-auto mt-12 max-w-md space-y-8 lg:hidden">
                        @foreach ($packages as $packageKey => $package)
                            @php $pop = $package['is_popular'] ?? false; @endphp
                            <section class="p-8 {{ $pop ? 'rounded-2xl bg-slate-50 ring-1 ring-inset ring-slate-200' : '' }}">
                                <h3 class="text-sm font-semibold text-slate-900">{{ $package['name'] }}</h3>
                                @if ($isCs($package))
                                    <p class="mt-1 text-xs font-medium text-indigo-600">{{ __('landing.pricing.regulated_teams') }}</p>
                                @endif

                                <p class="mt-2 flex items-baseline gap-x-1 text-slate-900">
                                    @if ($isCs($package))
                                        <span class="text-4xl font-semibold">{{ __('landing.pricing.custom') }}</span>
                                    @else
                                        <span class="text-4xl font-semibold">{{ $billingConfig['currencySymbol'] }}<span
                                                x-text="annual ? '{{ number_format($package['yearly_price'] / 12 / 100) }}' : '{{ number_format($package['monthly_price'] / 100) }}'"></span></span>
                                        <span class="text-sm font-semibold text-slate-500">{{ __('landing.pricing.per_month') }}</span>
                                    @endif
                                </p>
                                <div class="mt-1 h-5 text-xs text-slate-500">
                                    @if ($isCs($package))
                                        {{ __('landing.pricing.lets_talk') }}
                                    @else
                                        <span x-show="annual">{{ __('landing.pricing.billed_yearly_at') }} {{ $billingConfig['currencySymbol'] }}{{ number_format($package['yearly_price'] / 100, 0) }}</span>
                                    @endif
                                </div>

                                @if ($isCs($package))
                                    <a href="{{ route('contact') }}"
                                        class="mt-8 block w-full cursor-pointer rounded-md bg-blue-600 px-3 py-2 text-center text-sm font-semibold text-white hover:bg-blue-700">
                                        {{ __('landing.pricing.contact_sales') }}
                                    </a>
                                @else
                                    <form method="POST" action="{{ route('trial.checkout') }}" class="trial-form">
                                        @csrf
                                        <input type="hidden" name="package" value="{{ $packageKey }}">
                                        <input type="hidden" name="yearly" class="yearly-input" value="0">
                                        <button type="submit"
                                            class="mt-8 block w-full cursor-pointer rounded-md px-3 py-2 text-center text-sm font-semibold {{ $pop ? 'bg-blue-600 text-white hover:bg-blue-700' : 'text-blue-600 ring-1 ring-inset ring-blue-200 hover:ring-blue-300' }}"
                                            onclick="this.form.querySelector('.yearly-input').value = document.getElementById('toggle').checked ? '1' : '0'">
                                            {{ __('landing.pricing.start_free_trial') }}
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
                                    <div class="flex px-4" style="margin-inline-start: {{ $popMarginPct }}%; width: {{ $colPct }}%;" aria-hidden="true">
                                        <div class="w-full rounded-t-xl border-x border-t border-blue-200 bg-blue-50/60"></div>
                                    </div>
                                </div>
                            @endif

                            <table class="w-full table-fixed border-separate border-spacing-x-8 text-start">
                                <caption class="sr-only">{{ __('landing.pricing.comparison_caption') }}</caption>
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
                                                @if ($isCs($package))
                                                    <span class="mt-1 block text-xs font-medium text-indigo-600">{{ __('landing.pricing.regulated_teams') }}</span>
                                                @endif
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>

                                <tbody>
                                    <!-- Price + CTA -->
                                    <tr>
                                        <th scope="row"><span class="sr-only">{{ __('landing.pricing.price_sr') }}</span></th>
                                        @foreach ($packages as $packageKey => $package)
                                            @php $pop = $package['is_popular'] ?? false; @endphp
                                            <td class="px-6 pt-2 xl:px-8">
                                                @if ($isCs($package))
                                                    <div class="flex items-baseline gap-x-1 text-slate-900">
                                                        <span class="text-4xl font-semibold">{{ __('landing.pricing.custom') }}</span>
                                                    </div>
                                                    <div class="mt-1 h-5 text-xs text-slate-500">{{ __('landing.pricing.lets_talk') }}</div>
                                                    <a href="{{ route('contact') }}"
                                                        class="mt-8 block w-full cursor-pointer rounded-md bg-blue-600 px-3 py-2 text-center text-sm font-semibold text-white hover:bg-blue-700">
                                                        {{ __('landing.pricing.contact_sales') }}
                                                    </a>
                                                @else
                                                    <div class="flex items-baseline gap-x-1 text-slate-900">
                                                        <span class="text-4xl font-semibold">{{ $billingConfig['currencySymbol'] }}<span
                                                                x-text="annual ? '{{ number_format($package['yearly_price'] / 12 / 100) }}' : '{{ number_format($package['monthly_price'] / 100) }}'"></span></span>
                                                        <span class="text-sm font-semibold text-slate-500">{{ __('landing.pricing.per_month') }}</span>
                                                    </div>
                                                    <div class="mt-1 h-5 text-xs text-slate-500">
                                                        <span x-show="annual">{{ __('landing.pricing.billed_yearly_at') }} {{ $billingConfig['currencySymbol'] }}{{ number_format($package['yearly_price'] / 100, 0) }}</span>
                                                    </div>
                                                    <form method="POST" action="{{ route('trial.checkout') }}" class="trial-form">
                                                        @csrf
                                                        <input type="hidden" name="package" value="{{ $packageKey }}">
                                                        <input type="hidden" name="yearly" class="yearly-input" value="0">
                                                        <button type="submit"
                                                            class="mt-8 block w-full cursor-pointer rounded-md px-3 py-2 text-center text-sm font-semibold {{ $pop ? 'bg-blue-600 text-white hover:bg-blue-700' : 'text-blue-600 ring-1 ring-inset ring-blue-200 hover:ring-blue-300' }}"
                                                            onclick="this.form.querySelector('.yearly-input').value = document.getElementById('toggle').checked ? '1' : '0'">
                                                            {{ __('landing.pricing.start_free_trial') }}
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
                                                                <span tabindex="0" role="button" aria-label="{{ __('landing.pricing.what_is', ['label' => $row['label']]) }}"
                                                                    class="flex size-4 cursor-help items-center justify-center rounded-full bg-slate-200 text-[10px] font-semibold text-slate-500 hover:bg-slate-300">?</span>
                                                                <span role="tooltip"
                                                                    class="pointer-events-none absolute bottom-full start-0 z-20 mb-2 w-64 rounded-lg bg-slate-900 px-3 py-2 text-xs font-normal leading-snug text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100">
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
                                                                <span class="sr-only">{{ __('landing.pricing.included_in', ['plan' => $package['name']]) }}</span>
                                                            @else
                                                                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="mx-auto size-5 text-slate-300">
                                                                    <path fill-rule="evenodd" clip-rule="evenodd" d="{{ $minusPath }}" />
                                                                </svg>
                                                                <span class="sr-only">{{ __('landing.pricing.not_included_in', ['plan' => $package['name']]) }}</span>
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
