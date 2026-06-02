<!-- Pricing -->
@if ($pricing && isset($packages))
    <section class="relative" id="pricing">

        <!-- Dark background -->
        <div class="absolute inset-0 bg-slate-900 pointer-events-none -z-10 h-1/3 lg:h-2/3" aria-hidden="true"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6">
            <div class="py-12 md:py-20">

                <!-- Section header -->
                <div class="max-w-3xl mx-auto text-center pb-12">
                    <h2 class="h2 font-display text-slate-100">
                        Pricing
                    </h2>
                </div>

                <!-- Pricing tables -->
                <div x-data="{ annual: true }">

                    <!-- Pricing toggle -->
                    <div class="flex justify-center items-center space-x-4 sm:space-x-7 mb-16">
                        <div class="text-sm text-slate-500 font-medium text-right min-w-[8rem]">Pay
                            Monthly
                        </div>
                        <div class="form-switch shrink-0">
                            <input type="checkbox" id="toggle" class="sr-only" x-model="annual" />
                            <label class="bg-slate-700" for="toggle">
                                <span aria-hidden="true"></span>
                                <span class="sr-only">Pay annually</span>
                            </label>
                        </div>
                        <div class="text-sm text-slate-500 font-medium min-w-[8rem]">Pay Yearly <span
                                class="text-emerald-500">(-{{ $billingConfig['ui']['yearly_savings_percentage'] ?? 50 }}%)</span>
                        </div>
                    </div>

                    <div
                        class="max-w-sm mx-auto grid gap-8 lg:grid-cols-{{ min(count($packages), 4) }} lg:gap-6 items-start lg:max-w-none pt-4">

                        @foreach ($packages as $packageKey => $package)
                            @php
                                $numericIndex = $loop->index;
                                // Get all LLM models for tooltip
                                $llmModels = \Domain\Llm\Models\Llm::select('descriptive_name')
                                    ->orderBy('descriptive_name')
                                    ->get()
                                    ->pluck('descriptive_name')
                                    ->toArray();
                            @endphp

                            <div class="relative flex flex-col h-full px-6 py-5 bg-white shadow-lg" data-aos="fade-up"
                                @if ($numericIndex > 0) data-aos-delay="{{ (int) $numericIndex * 100 }}" @endif>
                                @if ($package['is_popular'])
                                    <div class="absolute top-0 right-0 mr-6 -mt-4">
                                        <div
                                            class="inline-flex text-sm font-medium py-1 px-3 text-emerald-700 bg-emerald-200 rounded-full">
                                            {{ $billingConfig['ui']['popular_badge_text'] ?? 'Most Popular' }}
                                        </div>
                                    </div>
                                @endif


                                <div class="mb-4 pb-4 border-b border-slate-200">
                                    <div class="text-lg font-medium text-slate-800 mb-1">{{ $package['name'] }}</div>
                                    <div class="inline-flex items-baseline mb-3">
                                        <span
                                            class="h3 font-medium text-slate-500">{{ $billingConfig['currencySymbol'] }}</span>
                                        <span
                                            x-text="annual ? '{{ number_format($package['yearly_price'] / 12 / 100) }}' : '{{ number_format($package['monthly_price'] / 100) }}'"
                                            class="h2 leading-7 font-display text-slate-800"></span>
                                        <span class="font-medium text-slate-400">/mo</span>
                                    </div>
                                    <div class="text-xs -mt-2 mb-2 h-5">
                                        <span x-show="annual" class="text-slate-400">
                                            Billed yearly at
                                            {{ $billingConfig['currencySymbol'] }}{{ number_format($package['yearly_price'] / 100, 0) }}
                                        </span>
                                    </div>
                                    <div class="text-slate-500">{{ $package['description'] }}</div>
                                </div>

                                <div class="p-3 rounded-sm bg-slate-50">
                                    <form method="POST" action="{{ route('trial.checkout') }}"
                                        class="w-full trial-form">
                                        @csrf
                                        <input type="hidden" name="package" value="{{ $packageKey }}">
                                        <input type="hidden" name="yearly" class="yearly-input" value="0">
                                        <button type="submit"
                                            class="btn-sm text-white bg-blue-600 hover:bg-blue-700 w-full group cursor-pointer"
                                            onclick="this.form.querySelector('.yearly-input').value = document.getElementById('toggle').checked ? '1' : '0'">
                                            Start 7-Day Free Trial <span
                                                class="tracking-normal text-blue-300 group-hover:translate-x-0.5 transition-transform duration-150 ease-in-out ml-1"></span>
                                        </button>
                                    </form>
                                </div>

                                {{--                                <div class="font-medium mb-3"> --}}

                                {{--                                    @if ($numericIndex === 0) --}}
                                {{--                                        Features include: --}}
                                {{--                                    @else --}}
                                {{--                                        @php --}}
                                {{--                                            $previousPackage = $packages->values()[$numericIndex - 1]; --}}
                                {{--                                        @endphp --}}
                                {{--                                        All features of {{ $previousPackage['name'] }} plus: --}}
                                {{--                                    @endif --}}
                                {{--                                </div> --}}
                                <div class="mt-3"></div>
                                <div class="grow">
                                    <!-- Monthly Features -->
                                    <ul class="text-slate-500 space-y-3 absolute" x-show="!annual">
                                        @foreach ($package['features_monthly'] as $feature)
                                            @php
                                                $isNewFeature = false;
                                                if ($numericIndex > 0) {
                                                    $previousPackage = $packages->values()[$numericIndex - 1];
                                                    $isNewFeature = !in_array(
                                                        $feature,
                                                        $previousPackage['features_monthly'],
                                                    );
                                                }

                                                // Check if the feature needs a tooltip
                                                $tooltipText = null;
                                                $tooltipIsList = false;
                                                $featureLower = strtolower($feature);

                                                if (stripos($featureLower, 'model') !== false) {
                                                    $tooltipText = $llmModels;
                                                    $tooltipIsList = true;
                                                } elseif (stripos($featureLower, 'tone analysis') !== false) {
                                                    $tooltipText =
                                                        'Automatically detects and analyzes the emotional tone of conversations to help maintain appropriate communication style and professionalism.';
                                                } elseif (stripos($featureLower, 'sentiment analysis') !== false) {
                                                    $tooltipText =
                                                        'Real-time analysis of customer sentiment (positive, negative, neutral) to help prioritize responses and improve customer satisfaction.';
                                                } elseif (stripos($featureLower, 'topic analysis') !== false) {
                                                    $tooltipText =
                                                        'Intelligently identifies and categorizes conversation topics to help organize support tickets, route inquiries, and gain insights into customer needs.';
                                                } elseif (stripos($featureLower, 'handoff') !== false) {
                                                    $tooltipText =
                                                        'Seamlessly transfer conversations from AI to human agents when complex issues arise or when customers request human assistance.';
                                                } elseif (stripos($featureLower, 'pulse') !== false || stripos($featureLower, 'conversation pulse') !== false) {
                                                    $tooltipText =
                                                        'Track real-time conversation health metrics including sentiment trends, emotional tone shifts, and conversation flow to proactively identify and address customer concerns.';
                                                }
                                            @endphp
                                            <li class="flex items-center">
                                                <svg class="w-3 h-3 fill-current text-emerald-500 mr-3 shrink-0"
                                                    viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M10.28 2.28L3.989 8.575 1.695 6.28A1 1 0 00.28 7.695l3 3a1 1 0 001.414 0l7-7A1 1 0 0010.28 2.28z" />
                                                </svg>

                                                @if ($tooltipText)
                                                    <div class="relative group" x-data="{ tooltipOpen: false }" @click.away="tooltipOpen = false">
                                                        <span
                                                            @click="tooltipOpen = !tooltipOpen"
                                                            class="@if ($isNewFeature) font-medium text-slate-700 @else text-slate-500 @endif border-b border-dotted border-slate-400 cursor-help">{{ $feature }}</span>

                                                        <!-- Tooltip -->
                                                        <div
                                                            x-show="tooltipOpen"
                                                            x-transition
                                                            @mouseenter="tooltipOpen = true"
                                                            @mouseleave="tooltipOpen = false"
                                                            class="absolute top-full left-0 mt-2 px-4 py-2.5 bg-slate-900 text-white rounded-lg shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-10 w-64"
                                                            :class="{ '!opacity-100 !visible': tooltipOpen }">
                                                            @if ($tooltipIsList && is_array($tooltipText))
                                                                <div class="text-xs text-slate-100">
                                                                    <div class="font-medium text-emerald-400 mb-2">
                                                                        Available Models:</div>
                                                                    <ul class="space-y-1.5">
                                                                        @foreach ($tooltipText as $model)
                                                                            <li class="flex items-start">
                                                                                <span
                                                                                    class="text-emerald-400 mr-2">•</span>
                                                                                <span>{{ $model }}</span>
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                </div>
                                                            @else
                                                                <div class="text-xs text-slate-100 leading-relaxed">
                                                                    {{ $tooltipText }}
                                                                </div>
                                                            @endif
                                                            <!-- Tooltip Arrow -->
                                                            <div class="absolute bottom-full left-4 mb-px">
                                                                <div
                                                                    class="border-4 border-transparent border-b-slate-900">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <span
                                                        @if ($isNewFeature) class="font-medium text-slate-700" @endif>{{ $feature }}</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>

                                    <!-- Yearly Features -->
                                    <ul class="text-slate-500 space-y-3 absolute" x-show="annual">
                                        @foreach ($package['features_yearly'] as $feature)
                                            @php
                                                $isNewFeature = false;
                                                if ($numericIndex > 0) {
                                                    $previousPackage = $packages->values()[$numericIndex - 1];
                                                    $isNewFeature = !in_array(
                                                        $feature,
                                                        $previousPackage['features_yearly'],
                                                    );
                                                }

                                                // Check if the feature needs a tooltip
                                                $tooltipText = null;
                                                $tooltipIsList = false;
                                                $featureLower = strtolower($feature);

                                                if (stripos($feature, 'message') !== false) {
                                                    $tooltipText =
                                                        $feature . ' will be credited to your account yearly';
                                                } elseif (stripos($featureLower, 'model') !== false) {
                                                    $tooltipText = $llmModels;
                                                    $tooltipIsList = true;
                                                } elseif (stripos($featureLower, 'tone analysis') !== false) {
                                                    $tooltipText =
                                                        'Automatically detects and analyzes the emotional tone of conversations to help maintain appropriate communication style and professionalism.';
                                                } elseif (stripos($featureLower, 'sentiment analysis') !== false) {
                                                    $tooltipText =
                                                        'Real-time analysis of customer sentiment (positive, negative, neutral) to help prioritize responses and improve customer satisfaction.';
                                                } elseif (stripos($featureLower, 'topic analysis') !== false) {
                                                    $tooltipText =
                                                        'Intelligently identifies and categorizes conversation topics to help organize support tickets, route inquiries, and gain insights into customer needs.';
                                                } elseif (stripos($featureLower, 'handoff') !== false) {
                                                    $tooltipText =
                                                        'Seamlessly transfer conversations from AI to human agents when complex issues arise or when customers request human assistance.';
                                                } elseif (stripos($featureLower, 'pulse') !== false || stripos($featureLower, 'conversation pulse') !== false) {
                                                    $tooltipText =
                                                        'Track real-time conversation health metrics including sentiment trends, emotional tone shifts, and conversation flow to proactively identify and address customer concerns.';
                                                }
                                            @endphp
                                            <li class="flex items-center">
                                                <svg class="w-3 h-3 fill-current text-emerald-500 mr-3 shrink-0"
                                                    viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M10.28 2.28L3.989 8.575 1.695 6.28A1 1 0 00.28 7.695l3 3a1 1 0 001.414 0l7-7A1 1 0 0010.28 2.28z" />
                                                </svg>

                                                @if ($tooltipText)
                                                    <div class="relative group" x-data="{ tooltipOpen: false }" @click.away="tooltipOpen = false">
                                                        <span
                                                            @click="tooltipOpen = !tooltipOpen"
                                                            class="@if ($isNewFeature) font-medium text-slate-700 @else text-slate-500 @endif border-b border-dotted border-slate-400 cursor-help">{{ $feature }}</span>

                                                        <!-- Tooltip -->
                                                        <div
                                                            x-show="tooltipOpen"
                                                            x-transition
                                                            @mouseenter="tooltipOpen = true"
                                                            @mouseleave="tooltipOpen = false"
                                                            class="absolute top-full left-0 mt-2 px-4 py-2.5 bg-slate-900 text-white rounded-lg shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-10 w-64"
                                                            :class="{ '!opacity-100 !visible': tooltipOpen }">
                                                            @if ($tooltipIsList && is_array($tooltipText))
                                                                <div class="text-xs text-slate-100">
                                                                    <div class="font-medium text-emerald-400 mb-2">
                                                                        Available Models:</div>
                                                                    <ul class="space-y-1.5">
                                                                        @foreach ($tooltipText as $model)
                                                                            <li class="flex items-start">
                                                                                <span
                                                                                    class="text-emerald-400 mr-2">•</span>
                                                                                <span>{{ $model }}</span>
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                </div>
                                                            @else
                                                                <div class="text-xs text-slate-100 leading-relaxed">
                                                                    {{ $tooltipText }}
                                                                </div>
                                                            @endif
                                                            <!-- Tooltip Arrow -->
                                                            <div class="absolute bottom-full left-4 mb-px">
                                                                <div
                                                                    class="border-4 border-transparent border-b-slate-900">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <span
                                                        @if ($isNewFeature) class="font-medium text-slate-700" @endif>{{ $feature }}</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>

                                    <!-- Invisible spacer to maintain height based on the longest feature list -->
                                    <ul class="text-slate-500 space-y-3 invisible">
                                        @php
                                            $maxFeatures = max(
                                                count($package['features_monthly']),
                                                count($package['features_yearly']),
                                            );
                                        @endphp
                                        @for ($i = 0; $i < $maxFeatures; $i++)
                                            <li class="flex items-center">
                                                <svg class="w-3 h-3 fill-current text-emerald-500 mr-3 shrink-0"
                                                    viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M10.28 2.28L3.989 8.575 1.695 6.28A1 1 0 00.28 7.695l3 3a1 1 0 001.414 0l7-7A1 1 0 0010.28 2.28z" />
                                                </svg>
                                                <span>Placeholder feature</span>
                                            </li>
                                        @endfor
                                    </ul>
                                </div>

                            </div>
                        @endforeach

                    </div>

                </div>

                <!-- Common Features -->
                @if (isset($billingConfig['commonFeatures']) && count($billingConfig['commonFeatures']) > 0)
                    <div class="mt-16 max-w-4xl mx-auto">
                        <div class="bg-white rounded-lg shadow-sm p-8">
                            <h3 class="text-lg font-medium text-slate-800 mb-6 text-center">
                                What's included with all plans
                            </h3>
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($billingConfig['commonFeatures'] as $feature)
                                    <div class="flex items-center gap-3">
                                        <svg class="w-4 h-4 fill-current text-emerald-500 shrink-0" viewBox="0 0 12 12"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path
                                                d="M10.28 2.28L3.989 8.575 1.695 6.28A1 1 0 00.28 7.695l3 3a1 1 0 001.414 0l7-7A1 1 0 0010.28 2.28z" />
                                        </svg>
                                        <span class="text-slate-600 text-sm">{{ $feature }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </section>
@endif
