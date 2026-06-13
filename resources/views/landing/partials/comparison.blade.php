<!-- Comparison: reframes the decision from "cheapest chatbot" to "compliant by default" -->
<section class="relative overflow-hidden bg-gradient-to-b from-white via-indigo-50/30 to-white pt-16 sm:pt-24 pb-10">
    <!-- Soft decorative glows (match the integrations section) -->
    <div class="absolute inset-0 pointer-events-none">
        <div class="absolute top-1/4 right-1/4 w-72 h-72 bg-indigo-200/40 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 left-1/4 w-72 h-72 bg-purple-200/40 rounded-full blur-3xl"></div>
    </div>

    <div class="relative max-w-5xl mx-auto px-4 sm:px-6">

        <!-- Heading -->
        <div class="text-center max-w-3xl mx-auto mb-10 md:mb-14">
            <span class="block text-sm font-bold uppercase tracking-widest text-indigo-600 mb-4">
                {{ __('landing.comparison.eyebrow') }}
            </span>
            <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold text-slate-900 leading-tight tracking-tight">
                {{ __('landing.comparison.title') }}
            </h2>
            <p class="mt-6 text-base sm:text-lg text-slate-600 leading-relaxed">
                {{ __('landing.comparison.subtitle') }}
            </p>
        </div>

        <div class="max-w-3xl mx-auto overflow-x-auto border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50">
                        <th class="text-start font-medium text-slate-500 px-4 sm:px-6 py-4"></th>
                        <th class="text-start font-semibold text-indigo-700 px-4 sm:px-6 py-4 bg-indigo-50/60">
                            {{ __('landing.comparison.col_chatvia') }}
                        </th>
                        <th class="text-start font-semibold text-slate-500 px-4 sm:px-6 py-4">
                            {{ __('landing.comparison.col_us') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (__('landing.comparison.rows') as $row)
                        <tr class="border-t border-slate-100">
                            <th scope="row" class="text-start font-medium text-slate-700 px-4 sm:px-6 py-4">
                                {{ $row['label'] }}
                            </th>
                            <td class="px-4 sm:px-6 py-4 bg-indigo-50/40">
                                <span class="inline-flex items-center gap-2 font-medium text-slate-900">
                                    <svg class="w-4 h-4 flex-none text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    {{ $row['ours'] }}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                <span class="inline-flex items-center gap-2 text-slate-500">
                                    <svg class="w-4 h-4 flex-none text-slate-300" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    {{ $row['theirs'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>
</section>
