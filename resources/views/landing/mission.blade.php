@extends('landing.layouts.app')

@section('title', 'Our Mission — EU-hosted AI you can trust | ' . config('app.name'))

@section('description', 'Why Chatvia is built fully in the EU, on open-weight models, with security first. The data-leakage events at US AI providers that made us build a European alternative.')

@section('og_title', 'Our Mission — EU-hosted AI you can trust')
@section('og_description', 'AI should not mean handing your private data to US companies. Chatvia runs fully in the EU, on open-weight models, with security built in from the start.')

@section('content')
    <div class="flex flex-col min-h-screen overflow-hidden">

        @include('landing.partials.header')

        <main class="grow">

            <!-- Hero -->
            <section class="relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-b from-slate-50 via-white to-indigo-50/30 pointer-events-none"></div>

                <div class="relative max-w-4xl mx-auto px-5 sm:px-6">
                    <div class="pt-28 pb-12 md:pt-32 md:pb-16 text-center">

                        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-indigo-50 border border-indigo-100 rounded-full text-xs sm:text-sm font-semibold text-indigo-700 uppercase tracking-wider mb-6">
                            Our Mission
                        </div>

                        <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-slate-900 mb-6 leading-tight tracking-tight">
                            Your data stays yours.
                            <span class="block">And it stays in
                                <span class="relative inline-block">
                                    <span class="relative z-10 text-indigo-600">Europe</span>
                                    <span class="absolute bottom-1 left-0 right-0 h-3 sm:h-4 bg-linear-to-r from-indigo-200 via-indigo-300 to-purple-200 -rotate-1 opacity-60"></span>
                                </span>.
                            </span>
                        </h1>

                        <p class="text-lg md:text-xl text-slate-600 max-w-2xl mx-auto leading-relaxed">
                            We built Chatvia because AI should not mean handing your private data to US
                            companies. Chatvia runs fully in the EU, on open-weight models, with security
                            built in from the start.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Why we built it -->
            <section class="bg-white">
                <div class="max-w-3xl mx-auto px-5 sm:px-6 py-14 md:py-20">
                    <h2 class="text-2xl sm:text-3xl md:text-4xl font-display text-slate-900 mb-6">
                        Why we built Chatvia
                    </h2>
                    <div class="space-y-5 text-lg text-slate-600 leading-relaxed">
                        <p>
                            AI changed how companies work. People now chat with tools to write, plan, and
                            find answers. But there is a hidden cost.
                        </p>
                        <p>
                            To use most AI tools, you send your data to a US company. You often do not know
                            where it goes, who can read it, or whether it is used to train the next model.
                            For a business, that is a real risk — your documents, your customers, your
                            internal know-how.
                        </p>
                        <p class="text-slate-900 font-semibold">
                            We saw this go wrong again and again. So we built a different kind of AI
                            platform — one that keeps your data in Europe and under your control.
                        </p>
                    </div>
                </div>
            </section>

            <!-- What already happened (the evidence) -->
            <section class="bg-slate-50 border-y border-slate-100">
                <div class="max-w-5xl mx-auto px-5 sm:px-6 py-14 md:py-20">

                    <div class="max-w-3xl mx-auto text-center mb-12">
                        <h2 class="text-2xl sm:text-3xl md:text-4xl font-display text-slate-900 mb-4">
                            This is not theory. It already happened.
                        </h2>
                        <p class="text-lg text-slate-500">
                            These are public, documented events at the biggest US AI providers. They are
                            the reason Chatvia exists.
                        </p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">

                        <!-- OpenAI breach -->
                        <div class="bg-white rounded-2xl border border-slate-200/60 p-6 sm:p-7 flex flex-col">
                            <h3 class="text-lg font-semibold text-slate-900 mb-2">A bug exposed private chats</h3>
                            <p class="text-slate-600 leading-relaxed grow">
                                In March 2023, a bug in ChatGPT let some users see other people's chat
                                history. It also exposed some subscribers' names, email addresses, and
                                partial payment details.
                            </p>
                            <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                <a href="https://openai.com/index/march-20-chatgpt-outage/" target="_blank" rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 font-medium text-indigo-600 hover:text-indigo-700">
                                    OpenAI's own report
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                </a>
                                <a href="https://www.bleepingcomputer.com/news/security/openai-chatgpt-payment-data-leak-caused-by-open-source-bug/" target="_blank" rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 font-medium text-slate-500 hover:text-slate-700">
                                    BleepingComputer
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                </a>
                            </div>
                        </div>

                        <!-- Samsung leak -->
                        <div class="bg-white rounded-2xl border border-slate-200/60 p-6 sm:p-7 flex flex-col">
                            <h3 class="text-lg font-semibold text-slate-900 mb-2">Staff leaked secret code</h3>
                            <p class="text-slate-600 leading-relaxed grow">
                                In April 2023, Samsung engineers pasted confidential source code into
                                ChatGPT. The data left the company. Samsung then banned these tools for
                                staff.
                            </p>
                            <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                <a href="https://techcrunch.com/2023/05/02/samsung-bans-use-of-generative-ai-tools-like-chatgpt-after-april-internal-data-leak/" target="_blank" rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 font-medium text-indigo-600 hover:text-indigo-700">
                                    TechCrunch
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                </a>
                            </div>
                        </div>

                        <!-- Italian regulator -->
                        <div class="bg-white rounded-2xl border border-slate-200/60 p-6 sm:p-7 flex flex-col">
                            <h3 class="text-lg font-semibold text-slate-900 mb-2">A regulator stepped in</h3>
                            <p class="text-slate-600 leading-relaxed grow">
                                Italy's data protection authority temporarily blocked ChatGPT in 2023. It
                                then fined OpenAI €15 million in 2024 for breaking EU privacy rules.
                            </p>
                            <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                <a href="https://www.garanteprivacy.it/home/docweb/-/docweb-display/docweb/10085432" target="_blank" rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 font-medium text-indigo-600 hover:text-indigo-700">
                                    Italian authority (the fine)
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                </a>
                                <a href="https://www.garanteprivacy.it/home/docweb/-/docweb-display/docweb/9870847" target="_blank" rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 font-medium text-slate-500 hover:text-slate-700">
                                    the 2023 block
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                </a>
                            </div>
                        </div>

                        <!-- CLOUD Act / Schrems II -->
                        <div class="bg-white rounded-2xl border border-slate-200/60 p-6 sm:p-7 flex flex-col">
                            <h3 class="text-lg font-semibold text-slate-900 mb-2">US law can reach your data</h3>
                            <p class="text-slate-600 leading-relaxed grow">
                                Under the US CLOUD Act, US authorities can force US companies to hand over
                                data — even when it sits on servers in Europe. Europe's top court (the
                                "Schrems II" ruling) found that sending personal data to the US does not
                                give it the protection EU law requires.
                            </p>
                            <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                <a href="https://www.eff.org/deeplinks/2018/02/cloud-act-dangerous-expansion-police-snooping-cross-border-data" target="_blank" rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 font-medium text-indigo-600 hover:text-indigo-700">
                                    EFF on the CLOUD Act
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                </a>
                                <a href="https://curia.europa.eu/jcms/upload/docs/application/pdf/2020-07/cp200091en.pdf" target="_blank" rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 font-medium text-slate-500 hover:text-slate-700">
                                    the Schrems II ruling
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            </section>

            <!-- How Chatvia is different -->
            <section class="bg-white">
                <div class="max-w-5xl mx-auto px-5 sm:px-6 py-14 md:py-20">

                    <div class="max-w-3xl mx-auto text-center mb-12">
                        <h2 class="text-2xl sm:text-3xl md:text-4xl font-display text-slate-900 mb-4">
                            How Chatvia is different
                        </h2>
                        <p class="text-lg text-slate-500">
                            Four promises, built into the product — not added on later.
                        </p>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">

                        <!-- EU hosting -->
                        <div class="bg-slate-50 rounded-2xl border border-slate-200/60 p-6 sm:p-7">
                            <div class="w-11 h-11 rounded-xl bg-white border border-slate-200 flex items-center justify-center mb-4">
                                <img src="{{ asset('images/flags/eu.svg') }}" alt="EU" class="w-6 h-6 rounded-sm" />
                            </div>
                            <h3 class="text-lg font-semibold text-slate-900 mb-2">Hosted fully in the EU</h3>
                            <p class="text-slate-600 leading-relaxed">
                                Your data lives in Europe. The app and database run in Germany. The AI runs
                                in France. Nothing is processed in the US.
                            </p>
                        </div>

                        <!-- Open-weight models -->
                        <div class="bg-slate-50 rounded-2xl border border-slate-200/60 p-6 sm:p-7">
                            <div class="w-11 h-11 rounded-xl bg-white border border-slate-200 flex items-center justify-center mb-4 text-indigo-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0-5.571 3-5.571-3" /></svg>
                            </div>
                            <h3 class="text-lg font-semibold text-slate-900 mb-2">Open-weight models</h3>
                            <p class="text-slate-600 leading-relaxed">
                                Chatvia runs on open-weight AI models on EU servers. Your prompts are not
                                sent to OpenAI or any US AI company, and they are never used to train
                                someone else's model.
                            </p>
                        </div>

                        <!-- Security first -->
                        <div class="bg-slate-50 rounded-2xl border border-slate-200/60 p-6 sm:p-7">
                            <div class="w-11 h-11 rounded-xl bg-white border border-slate-200 flex items-center justify-center mb-4 text-indigo-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                            </div>
                            <h3 class="text-lg font-semibold text-slate-900 mb-2">Security first</h3>
                            <p class="text-slate-600 leading-relaxed">
                                Encryption in transit and at rest. Multi-factor login and passkeys. Every
                                sensitive AI action is written to an audit log you can review.
                            </p>
                            <a href="{{ route('security') }}" class="inline-flex items-center gap-1 mt-3 text-sm font-medium text-indigo-600 hover:text-indigo-700">
                                Read our security page
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                            </a>
                        </div>

                        <!-- GDPR by default -->
                        <div class="bg-slate-50 rounded-2xl border border-slate-200/60 p-6 sm:p-7">
                            <div class="w-11 h-11 rounded-xl bg-white border border-slate-200 flex items-center justify-center mb-4 text-indigo-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                            </div>
                            <h3 class="text-lg font-semibold text-slate-900 mb-2">GDPR by default</h3>
                            <p class="text-slate-600 leading-relaxed">
                                You are the data controller; we are the processor. A signable Data
                                Processing Agreement is ready for you, with EU residency built in.
                            </p>
                            <a href="{{ route('dpa') }}" class="inline-flex items-center gap-1 mt-3 text-sm font-medium text-indigo-600 hover:text-indigo-700">
                                Generate your DPA
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                            </a>
                        </div>

                    </div>
                </div>
            </section>

            <!-- Why open-weight matters -->
            <section class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900">
                <div class="max-w-4xl mx-auto px-5 sm:px-6 py-14 md:py-20">
                    <h2 class="text-2xl sm:text-3xl md:text-4xl font-display text-white mb-6">
                        Why open-weight models matter
                    </h2>
                    <p class="text-lg text-slate-300 leading-relaxed mb-8 max-w-2xl">
                        Most AI tools run on closed models you cannot see or move. You have to trust the
                        company behind them. Open-weight models are different — the model runs on our own
                        EU servers. That means three things for you:
                    </p>
                    <div class="grid gap-5 sm:grid-cols-3">
                        <div class="bg-white/5 border border-white/10 rounded-xl p-5">
                            <div class="text-indigo-300 font-semibold mb-1">Stays in the EU</div>
                            <p class="text-slate-300 text-sm leading-relaxed">Your data never leaves Europe to reach the model.</p>
                        </div>
                        <div class="bg-white/5 border border-white/10 rounded-xl p-5">
                            <div class="text-indigo-300 font-semibold mb-1">Not training data</div>
                            <p class="text-slate-300 text-sm leading-relaxed">Your prompts and content are not used to train a foundation model.</p>
                        </div>
                        <div class="bg-white/5 border border-white/10 rounded-xl p-5">
                            <div class="text-indigo-300 font-semibold mb-1">No lock-in</div>
                            <p class="text-slate-300 text-sm leading-relaxed">You are not tied to a single US vendor's model or price.</p>
                        </div>
                    </div>
                    <p class="text-base text-slate-400 leading-relaxed mt-8 max-w-2xl">
                        We choose strong open-weight models on purpose. They give you capable AI without
                        giving up control of your data.
                    </p>
                </div>
            </section>

            <!-- CTA -->
            <section class="relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-50 via-white to-purple-50"></div>
                <div class="relative max-w-3xl mx-auto px-5 sm:px-6 py-16 md:py-24 text-center">
                    <h2 class="text-2xl sm:text-3xl md:text-4xl font-display text-slate-900 mb-5">
                        AI you can trust with your data.
                    </h2>
                    <p class="text-lg text-slate-600 mb-8 max-w-xl mx-auto">
                        Build AI agents trained on your data, hosted fully in the EU, on open-weight
                        models. Your data stays in Europe and under your control.
                    </p>
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                        <a href="{{ route('home') }}#pricing"
                            class="inline-flex items-center gap-2 px-8 py-4 text-lg font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-all duration-200 shadow-lg shadow-indigo-500/25 hover:shadow-xl hover:shadow-indigo-500/30 hover:-translate-y-0.5">
                            Start your free trial
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                        </a>
                        <a href="{{ route('security') }}"
                            class="inline-flex items-center gap-2 px-8 py-4 text-lg font-semibold text-slate-700 bg-white hover:bg-slate-50 rounded-xl border border-slate-200 transition-all duration-200 hover:shadow-md">
                            See how we protect your data
                        </a>
                    </div>
                </div>
            </section>

        </main>

        @include('landing.partials.footer')

    </div>
@endsection
