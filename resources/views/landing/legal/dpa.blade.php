@extends('landing.layouts.app')

@section('title', 'Data Processing Agreement (DPA) - ' . config('app.name'))
@section('description', 'Generate a customized GDPR Data Processing Agreement for your use of Chatvia. Fill in your company details and download it as a PDF.')

@section('content')
    <div class="flex flex-col min-h-screen overflow-hidden">

        @include('landing.partials.header')

        <main class="grow pt-28 pb-20">
            <div class="max-w-4xl mx-auto px-5 sm:px-6">

                <!-- Heading -->
                <div class="text-center mb-10">
                    <h1 class="text-4xl md:text-5xl font-extrabold text-slate-900 mb-4">Data Processing Agreement</h1>
                    <p class="text-lg text-slate-600">
                        Generate a customized, GDPR-compliant DPA for your use of Chatvia. Fill in your company details
                        and download it as a PDF.
                    </p>
                </div>

                <!-- Form -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8" x-data="{ company: '' }">
                    <h2 class="text-2xl font-bold text-slate-900 mb-1">Generate Your DPA</h2>
                    <p class="text-slate-600 text-sm mb-6">
                        Fill in your company details below and download a customized Data Processing Agreement.
                    </p>

                    <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900 mb-6">
                        <strong>Note:</strong> This DPA becomes effective only when signed by both parties. To have us
                        countersign, contact
                        <a href="mailto:info@chatvia.ai" class="font-medium underline">info@chatvia.ai</a>.
                    </div>

                    <form method="GET" action="{{ route('dpa.download') }}" class="space-y-5">
                        <div class="grid sm:grid-cols-2 gap-5">
                            <div>
                                <label for="company_name" class="block text-sm font-medium mb-1">
                                    Company Name <span class="text-rose-500">*</span>
                                </label>
                                <input id="company_name" name="company_name" x-model="company" type="text" required
                                    placeholder="Acme Corporation Ltd." class="form-input py-2 w-full" />
                            </div>
                            <div>
                                <label for="registration_number" class="block text-sm font-medium mb-1">Registration Number</label>
                                <input id="registration_number" name="registration_number" type="text"
                                    placeholder="12345678" class="form-input py-2 w-full" />
                            </div>
                        </div>

                        <div>
                            <label for="registered_address" class="block text-sm font-medium mb-1">Registered Address</label>
                            <input id="registered_address" name="registered_address" type="text"
                                placeholder="123 Business Street, City, Postcode" class="form-input py-2 w-full" />
                        </div>

                        <div class="grid sm:grid-cols-2 gap-5">
                            <div>
                                <label for="country" class="block text-sm font-medium mb-1">Country</label>
                                <input id="country" name="country" type="text" placeholder="Germany"
                                    class="form-input py-2 w-full" />
                            </div>
                            <div>
                                <label for="contact_email" class="block text-sm font-medium mb-1">Contact Email</label>
                                <input id="contact_email" name="contact_email" type="email"
                                    placeholder="legal@company.com" class="form-input py-2 w-full" />
                            </div>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-5">
                            <div>
                                <label for="signatory_name" class="block text-sm font-medium mb-1">Signatory Name</label>
                                <input id="signatory_name" name="signatory_name" type="text" placeholder="Jane Doe"
                                    class="form-input py-2 w-full" />
                            </div>
                            <div>
                                <label for="signatory_title" class="block text-sm font-medium mb-1">Signatory Title</label>
                                <input id="signatory_title" name="signatory_title" type="text"
                                    placeholder="CEO / Data Protection Officer" class="form-input py-2 w-full" />
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-4 pt-2">
                            <button type="submit" :disabled="!company.trim()"
                                class="btn-sm text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
                                </svg>
                                Download PDF
                            </button>
                            <span x-show="!company.trim()" class="text-slate-500 text-sm">
                                Enter your company name to enable download
                            </span>
                        </div>
                    </form>
                </div>

                <!-- Explainer (below the form) -->
                <div class="mt-12 space-y-10">
                    <div>
                        <h3 class="text-xl font-bold text-slate-900 mb-3">What is a DPA?</h3>
                        <p class="text-slate-600 leading-relaxed">
                            A Data Processing Agreement (DPA) is a legally binding contract between a data controller
                            (you) and a data processor (Chatvia) that outlines the terms and conditions for processing
                            personal data.
                        </p>
                        <p class="text-slate-600 leading-relaxed mt-3">
                            This agreement ensures compliance with data protection regulations such as GDPR, CCPA, and
                            other applicable privacy laws.
                        </p>
                    </div>

                    <div>
                        <h3 class="text-xl font-bold text-slate-900 mb-3">What's included</h3>
                        <ul class="grid sm:grid-cols-2 gap-x-6 gap-y-2 text-slate-600">
                            <li class="flex gap-2"><span class="text-blue-600 font-semibold">&check;</span> Data processing terms and obligations</li>
                            <li class="flex gap-2"><span class="text-blue-600 font-semibold">&check;</span> Security measures and technical safeguards</li>
                            <li class="flex gap-2"><span class="text-blue-600 font-semibold">&check;</span><span>Sub-processor information <span class="whitespace-nowrap">(<a href="{{ route('subprocessors') }}" class="text-blue-600 hover:underline">see list</a>)</span></span></li>
                            <li class="flex gap-2"><span class="text-blue-600 font-semibold">&check;</span> Data subject rights procedures</li>
                            <li class="flex gap-2"><span class="text-blue-600 font-semibold">&check;</span> Breach notification protocols</li>
                            <li class="flex gap-2"><span class="text-blue-600 font-semibold">&check;</span> International data transfer mechanisms</li>
                        </ul>
                    </div>

                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-5">
                        <h3 class="text-xl font-bold text-slate-900 mb-2">Questions?</h3>
                        <p class="text-slate-600">
                            If you have questions about our data processing practices or need a customized agreement,
                            please contact us at
                            <a href="mailto:info@chatvia.ai" class="text-blue-600 hover:underline">info@chatvia.ai</a>.
                        </p>
                    </div>
                </div>

            </div>
        </main>

        @include('landing.partials.footer')

    </div>
@endsection
