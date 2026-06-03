<!-- Pricing -->
<section id="pricing">
    <div class="py-12">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="relative max-w-3xl mx-auto text-center pb-12">
                <h2 class="font-inter-tight text-3xl md:text-4xl lg:text-5xl font-extrabold text-zinc-900 mb-4">
                    Start your journey today</h2>
                <p class="text-lg text-zinc-500">Start creating AI agents for free. Upgrade for
                    advanced features and team collaboration.</p>
                <div class="inline-flex items-center gap-2 text-sm text-emerald-700 bg-emerald-50 px-4 py-2 rounded-full border border-emerald-200 mt-4">
                    <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    No credit card required
                </div>
            </div>

            <!-- Pricing Toggle and Cards Container -->
            <div x-data="{ yearly: false }">
                <!-- Pricing Toggle -->
                <div class="flex justify-center mb-8">
                    <div class="flex items-center space-x-4 bg-zinc-100 rounded-lg p-1">
                        <button
                            @click="yearly = false"
                            :class="yearly ? 'text-zinc-500' : 'text-zinc-900 bg-white shadow-sm'"
                            class="px-4 py-2 rounded-md text-sm font-medium transition-all duration-200">
                            Monthly
                        </button>
                        <button
                            @click="yearly = true"
                            :class="yearly ? 'text-zinc-900 bg-white shadow-sm' : 'text-zinc-500'"
                            class="px-4 py-2 rounded-md text-sm font-medium transition-all duration-200">
                            Yearly
                            <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                Save {{ config('billing.ui.yearly_savings_percentage') }}%
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Pricing tiers -->
                <div>
                <div class="max-w-sm mx-auto grid gap-6 lg:grid-cols-{{ count(config('billing.packages')) }} items-start lg:max-w-none">
                    @php
                        $packages = config('billing.packages');
                        $packageOrder = ['free', 'starter', 'standard', 'pro'];
                    @endphp

                    @foreach($packageOrder as $packageKey)
                        @if(isset($packages[$packageKey]))
                            @php
                                $package = $packages[$packageKey];
                                $isPopular = $package['is_popular'] ?? false;
                                $isDark = $isPopular;
                                $textColor = $isDark ? 'text-white' : 'text-zinc-900';
                                $bgClass = $isDark ? 'bg-zinc-800' : 'border border-transparent [background:linear-gradient(var(--color-zinc-50),var(--color-zinc-50))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box]';
                                $features = $package['features']['monthly'] ?? [];
                            @endphp

                            <div class="h-full">
                                <div class="relative flex flex-col h-full p-6 rounded-lg {{ $bgClass }}">
                                    @if($isPopular)
                                        <img class="absolute right-6 -top-5 mix-blend-exclusion"
                                             src="{{ asset('images/landing/pricing-decoration.png') }}"
                                             alt="Pricing decoration" width="76" height="74" aria-hidden="true">
                                    @endif

                                    <div class="mb-4">
                                        <div class="text-lg {{ $textColor }} font-semibold mb-1">{{ $package['name'] }}</div>
                                        <div class="font-inter-tight mb-2">
                                            @if($package['monthly_price'] > 0)
                                                <div class="inline-flex items-baseline">
                                                    <span class="{{ $textColor }} font-bold text-2xl">$</span>
                                                    <span class="{{ $textColor }} font-bold text-3xl" x-text="yearly ? '{{ number_format($package['yearly_price'] / 100, 2) }}' : '{{ number_format($package['monthly_price'] / 100, 2) }}'"></span>
                                                    <span class="{{ $textColor }} font-medium" x-text="yearly ? '/year' : '/mo'"></span>
                                                </div>
                                                <div x-show="yearly" class="{{ $textColor }} text-xs mt-1 opacity-75">(billed yearly)</div>
                                            @else
                                                <div class="inline-flex items-baseline">
                                                    <span class="{{ $textColor }} font-bold text-3xl">$0</span>
                                                    <span class="{{ $textColor }} font-medium">/mo</span>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="{{ $textColor }}">{{ $package['description'] }}</div>
                                        @if($package['monthly_price'] > 0)
                                            <div x-show="yearly" class="text-sm text-emerald-600 font-medium mt-1">
                                                Save ${{ number_format(($package['monthly_price'] * 12 - $package['yearly_price']) / 100, 2) }} per year
                                            </div>
                                        @endif
                                    </div>

                                    <div class="grow">
                                        <div class="text-sm {{ $textColor }} font-medium mb-4">Includes:</div>
                                        <ul class="text-zinc-600 dark:text-zinc-400 text-sm space-y-3 grow">
                                            @foreach(array_slice($features, 0, 6) as $feature)
                                                @php
                                                    $featureLabel = is_array($feature) ? ($feature['label'] ?? '') : $feature;
                                                    $featureHref = is_array($feature) ? ($feature['href'] ?? null) : null;
                                                @endphp
                                                <li class="flex items-center">
                                                    <svg class="w-3 h-3 fill-emerald-500 mr-3 shrink-0" viewBox="0 0 12 12"
                                                         xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M10.28 2.28L3.989 8.575 1.695 6.28A1 1 0 00.28 7.695l3 3a1 1 0 001.414 0l7-7A1 1 0 0010.28 2.28z"/>
                                                    </svg>
                                                    @if ($featureHref)
                                                        <a href="{{ $featureHref }}" target="_blank" rel="noopener noreferrer" class="{{ $textColor }} underline decoration-dotted underline-offset-2">{{ $featureLabel }}</a>
                                                    @else
                                                        <span class="{{ $textColor }}">{{ $featureLabel }}</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>

                                    <div class="mt-8">
                                            <a class="btn text-zinc-100 bg-linear-to-r from-zinc-700 to-zinc-900 hover:from-zinc-900 hover:to-zinc-900 w-full shadow-sm"
                                               href="#pricing">
                                                Start Free Trial
                                            </a>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
</section>
