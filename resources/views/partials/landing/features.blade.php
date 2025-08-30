<!-- Features -->
<section id="features" class="relative bg-zinc-50">
    <div class="py-12 md:py-20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="max-w-3xl mx-auto text-center pb-12">
                <!-- Trust badges -->
                <div class="flex flex-row gap-3 justify-center mb-6 px-4">
                    <div class="inline-flex items-center justify-center gap-2 text-sm text-emerald-700 bg-emerald-50 px-4 py-2 rounded-full border border-emerald-200 whitespace-nowrap">
                        <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                  clip-rule="evenodd"/>
                        </svg>
                        <span class="text-center">GDPR compliant</span>
                    </div>
                    <div class="inline-flex items-center justify-center gap-2 text-sm text-emerald-700 bg-emerald-50 px-4 py-2 rounded-full border border-emerald-200 whitespace-nowrap">
                        <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                  clip-rule="evenodd"/>
                        </svg>
                        <span class="text-center">Hosted in EU</span>
                    </div>
                </div>
                
                <div class="inline-flex text-sm font-medium text-emerald-600 px-4 py-0.5 border border-transparent [background:linear-gradient(var(--color-emerald-50),var(--color-emerald-50))_padding-box,linear-gradient(120deg,var(--color-emerald-300),var(--color-emerald-100),var(--color-emerald-300))_border-box] rounded-full mb-4">
                    Your data stays private with RAG technology
                </div>
                <h2 class="font-inter-tight text-3xl md:text-4xl lg:text-5xl font-extrabold text-zinc-900 mb-4">
                    Powerful features for your AI agents
                </h2>
                <p class="text-lg text-zinc-500">Create custom AI agents that understand your data.</p>
                <p class="text-lg text-zinc-500">
                    Train on your documents or use widget agents for immediate deployment.
                </p>
            </div>
            <div x-data="{ tab: '1' }">
                <!-- Tabs buttons -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
                    <button
                            :class="tab !== '1' ? 'bg-zinc-100 opacity-60 hover:opacity-100 transition' : '[background:linear-gradient(var(--color-white),var(--color-white))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] shadow-xs rotate-1'"
                            class="text-left px-4 py-5 border border-transparent rounded-sm"
                            @click.prevent="tab = '1'"
                    >
                        <div class="flex items-center justify-between mb-1">
                            <div class="font-inter-tight font-semibold text-zinc-900">Training Agents</div>
                            <svg :class="tab !== '1' ? 'hidden' : ''" class="fill-zinc-400 shrink-0 ml-2"
                                 xmlns="http://www.w3.org/2000/svg" width="10" height="10">
                                <path d="M8.667.186H2.675a.999.999 0 0 0 0 1.998h3.581L.971 7.469a.999.999 0 1 0 1.412 1.412l5.285-5.285v3.58a.999.999 0 1 0 1.998 0V1.186a.999.999 0 0 0-.999-.999Z"/>
                            </svg>
                        </div>
                        <div class="text-sm text-zinc-500">Upload your documents and train AI agents on your
                            specific data and knowledge.
                        </div>
                    </button>
                    <button
                            :class="tab !== '2' ? 'bg-zinc-100 opacity-60 hover:opacity-100 transition' : '[background:linear-gradient(var(--color-white),var(--color-white))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] shadow-xs rotate-1'"
                            class="text-left px-4 py-5 border border-transparent rounded-sm"
                            @click.prevent="tab = '2'"
                    >
                        <div class="flex items-center justify-between mb-1">
                            <div class="font-inter-tight font-semibold text-zinc-900">Widget Agents</div>
                            <svg :class="tab !== '2' ? 'hidden' : ''" class="fill-zinc-400 shrink-0 ml-2"
                                 xmlns="http://www.w3.org/2000/svg" width="10" height="10">
                                <path d="M8.667.186H2.675a.999.999 0 0 0 0 1.998h3.581L.971 7.469a.999.999 0 1 0 1.412 1.412l5.285-5.285v3.58a.999.999 0 1 0 1.998 0V1.186a.999.999 0 0 0-.999-.999Z"/>
                            </svg>
                        </div>
                        <div class="text-sm text-zinc-500">Create embeddable chat widgets without
                            training on documents.
                        </div>
                    </button>
                    <button
                            :class="tab !== '3' ? 'bg-zinc-100 opacity-60 hover:opacity-100 transition' : '[background:linear-gradient(var(--color-white),var(--color-white))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] shadow-xs rotate-1'"
                            class="text-left px-4 py-5 border border-transparent rounded-sm"
                            @click.prevent="tab = '3'"
                    >
                        <div class="flex items-center justify-between mb-1">
                            <div class="font-inter-tight font-semibold text-zinc-900">Models</div>
                            <svg :class="tab !== '3' ? 'hidden' : ''" class="fill-zinc-400 shrink-0 ml-2"
                                 xmlns="http://www.w3.org/2000/svg" width="10" height="10">
                                <path d="M8.667.186H2.675a.999.999 0 0 0 0 1.998h3.581L.971 7.469a.999.999 0 1 0 1.412 1.412l5.285-5.285v3.58a.999.999 0 1 0 1.998 0V1.186a.999.999 0 0 0-.999-.999Z"/>
                            </svg>
                        </div>
                        <div class="text-sm text-zinc-500">
                            State-of-the-art AI models for intelligent, accurate responses.
                        </div>
                    </button>
                    <button
                            :class="tab !== '4' ? 'bg-zinc-100 opacity-60 hover:opacity-100 transition' : '[background:linear-gradient(var(--color-white),var(--color-white))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] shadow-xs rotate-1'"
                            class="text-left px-4 py-5 border border-transparent rounded-sm"
                            @click.prevent="tab = '4'"
                    >
                        <div class="flex items-center justify-between mb-1">
                            <div class="font-inter-tight font-semibold text-zinc-900">Team Collaboration</div>
                            <svg :class="tab !== '4' ? 'hidden' : ''" class="fill-zinc-400 shrink-0 ml-2"
                                 xmlns="http://www.w3.org/2000/svg" width="10" height="10">
                                <path d="M8.667.186H2.675a.999.999 0 0 0 0 1.998h3.581L.971 7.469a.999.999 0 1 0 1.412 1.412l5.285-5.285v3.58a.999.999 0 1 0 1.998 0V1.186a.999.999 0 0 0-.999-.999Z"/>
                            </svg>
                        </div>
                        <div class="text-sm text-zinc-500">Add team members, collaborate on agents, and share
                            custom starter messages.
                        </div>
                    </button>
                </div>
                <!-- Tabs items -->
                <div class="relative lg:max-w-none [mask-image:linear-gradient(white_0%,white_calc(100%-40px),_transparent_calc(100%-1px))] overflow-hidden">
                    <div class="relative flex flex-col pt-12 md:pt-20">
                        <!-- Item 1 -->
                        <div
                                class="w-full text-center"
                                x-show="tab === '1'"
                                x-transition:enter="transition ease-in-out duration-700 transform order-first"
                                x-transition:enter-start="opacity-0 -translate-y-4"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                x-transition:leave="transition ease-in-out duration-300 transform absolute"
                                x-transition:leave-start="opacity-100 translate-y-0"
                                x-transition:leave-end="opacity-0 translate-y-4"
                        >
                            <div class="inline-flex relative align-top">
                                <img class="w-full max-w-5xl h-auto rounded-t-lg border border-transparent [background:linear-gradient(var(--color-white),var(--color-white))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] box-content shadow-2xl"
                                     src="{{ asset('images/landing/feature-01.png') }}" alt="AI Agent Training - Upload documents and train custom AI agents" loading="lazy"/>
                            </div>
                        </div>
                        <!-- Item 2 -->
                        <div
                                class="w-full text-center"
                                x-show="tab === '2'"
                                x-transition:enter="transition ease-in-out duration-700 transform order-first"
                                x-transition:enter-start="opacity-0 -translate-y-4"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                x-transition:leave="transition ease-in-out duration-300 transform absolute"
                                x-transition:leave-start="opacity-100 translate-y-0"
                                x-transition:leave-end="opacity-0 translate-y-4"
                        >
                            <div class="inline-flex relative align-top">
                                <img class="w-full max-w-5xl h-auto rounded-t-lg border border-transparent [background:linear-gradient(var(--color-white),var(--color-white))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] box-content shadow-2xl"
                                     src="{{ asset('images/landing/feature-02.png') }}" alt="AI Agent Widgets - Create embeddable chat widgets" loading="lazy"/>
                            </div>
                        </div>
                        <!-- Item 3 -->
                        <div
                                class="w-full text-center"
                                x-show="tab === '3'"
                                x-transition:enter="transition ease-in-out duration-700 transform order-first"
                                x-transition:enter-start="opacity-0 -translate-y-4"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                x-transition:leave="transition ease-in-out duration-300 transform absolute"
                                x-transition:leave-start="opacity-100 translate-y-0"
                                x-transition:leave-end="opacity-0 translate-y-4"
                        >
                            <div class="inline-flex relative align-top">
                                <img class="w-full max-w-5xl h-auto rounded-t-lg border border-transparent [background:linear-gradient(var(--color-white),var(--color-white))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] box-content shadow-2xl"
                                     src="{{ asset('images/landing/feature-03.png') }}" alt="AI Models - Multiple LLM options including GPT, Claude, Gemini and more" loading="lazy"/>
                            </div>
                        </div>
                        <!-- Item 4 -->
                        <div
                                class="w-full text-center"
                                x-show="tab === '4'"
                                x-transition:enter="transition ease-in-out duration-700 transform order-first"
                                x-transition:enter-start="opacity-0 -translate-y-4"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                x-transition:leave="transition ease-in-out duration-300 transform absolute"
                                x-transition:leave-start="opacity-100 translate-y-0"
                                x-transition:leave-end="opacity-0 translate-y-4"
                        >
                            <div class="inline-flex relative align-top">
                                <img class="w-full max-w-5xl h-auto rounded-t-lg border border-transparent [background:linear-gradient(var(--color-white),var(--color-white))_padding-box,linear-gradient(120deg,var(--color-zinc-300),var(--color-zinc-100),var(--color-zinc-300))_border-box] box-content shadow-2xl"
                                     src="{{ asset('images/landing/feature-04.png') }}" alt="Team Collaboration - Add team members and collaborate on AI agents" loading="lazy"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
