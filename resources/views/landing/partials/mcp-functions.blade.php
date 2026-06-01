<!-- MCP & Functions Section -->
<section class="relative bg-white py-16 md:py-24 overflow-hidden">
    <!-- Decorative background -->
    <div class="absolute inset-0 pointer-events-none" aria-hidden="true">
        <div class="absolute top-0 right-1/4 w-72 h-72 bg-indigo-100/40 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-1/4 w-72 h-72 bg-purple-100/40 rounded-full blur-3xl"></div>
    </div>

    <div class="relative max-w-6xl mx-auto px-4 sm:px-6">

        <!-- Section header -->
        <div class="max-w-3xl mx-auto text-center mb-12 md:mb-16" data-aos="fade-up">
            <div class="inline-flex items-center gap-2 px-3 py-1 bg-indigo-50 border border-indigo-100 rounded-full text-xs font-semibold text-indigo-700 uppercase tracking-wider mb-4">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                Extensible by design
            </div>
            <h2 class="text-3xl sm:text-4xl md:text-5xl font-display text-slate-900 mb-5">
                Give your chatbot
                <span class="relative inline-block">
                    <span class="relative z-10 text-indigo-600 font-extrabold">real abilities</span>
                    <span class="absolute bottom-1 left-0 right-0 h-3 sm:h-4 bg-gradient-to-r from-indigo-200 via-indigo-300 to-purple-200 -rotate-1 opacity-60"></span>
                </span>
            </h2>
            <p class="text-lg md:text-xl text-slate-600 leading-relaxed">
                Don't stop at answers. Let your agent book meetings, check inventory, create tickets,
                and trigger workflows in your existing tools
            </p>
        </div>

        <!-- Two cards -->
        <div class="grid md:grid-cols-2 gap-6 lg:gap-8">

            <!-- Functions card -->
            <div class="group relative bg-gradient-to-br from-slate-50 to-indigo-50/40 rounded-2xl p-8 lg:p-10 border border-slate-200/80 hover:border-indigo-300 hover:shadow-xl hover:shadow-indigo-100/50 transition-all duration-300"
                data-aos="fade-up">

                <!-- Icon + label -->
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-12 h-12 bg-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/30">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold text-indigo-600 uppercase tracking-wider bg-indigo-100 px-2.5 py-1 rounded-md">
                        Custom Functions
                    </span>
                </div>

                <h3 class="text-2xl md:text-3xl font-display text-slate-900 mb-3">
                    Call any API your agent needs
                </h3>
                <p class="text-slate-600 leading-relaxed mb-6">
                    Define a webhook, describe what it does, and the AI decides when to call it.
                    Perfect for checking order status, looking up customer data, or triggering
                    actions in your own systems.
                </p>

                <!-- Code mockup -->
                <div class="bg-slate-900 rounded-lg p-4 font-mono text-xs leading-relaxed overflow-hidden shadow-inner">
                    <div class="flex items-center gap-1.5 mb-3 pb-2 border-b border-slate-700">
                        <div class="w-2 h-2 rounded-full bg-rose-400"></div>
                        <div class="w-2 h-2 rounded-full bg-amber-400"></div>
                        <div class="w-2 h-2 rounded-full bg-emerald-400"></div>
                        <span class="ml-2 text-slate-400 text-[10px]">check_order_status</span>
                    </div>
                    <div class="text-slate-300">
                        <span class="text-purple-300">POST</span>
                        <span class="text-emerald-300">https://api.shop.com/orders</span>
                    </div>
                    <div class="text-slate-300 mt-1">
                        <span class="text-slate-500">{</span> <span class="text-amber-300">"order_id"</span><span class="text-slate-500">:</span> <span class="text-emerald-300">"{{ '{' }}{{ '{' }} order_id {{ '}' }}{{ '}' }}"</span> <span class="text-slate-500">}</span>
                    </div>
                    <div class="text-slate-500 mt-2 text-[10px]"># Agent fills in {{ '{' }}{{ '{' }} order_id {{ '}' }}{{ '}' }} from the conversation</div>
                </div>

                <!-- Features list -->
                <ul class="mt-6 space-y-2.5">
                    <li class="flex items-start gap-2.5 text-sm text-slate-700">
                        <svg class="w-5 h-5 text-indigo-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span>Any HTTP endpoint — GET, POST, PUT, DELETE</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-sm text-slate-700">
                        <svg class="w-5 h-5 text-indigo-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span>Custom headers, auth, and parameter mapping</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-sm text-slate-700">
                        <svg class="w-5 h-5 text-indigo-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span>Reusable across all your agents</span>
                    </li>
                </ul>
            </div>

            <!-- MCP card -->
            <div class="group relative bg-gradient-to-br from-slate-50 to-purple-50/40 rounded-2xl p-8 lg:p-10 border border-slate-200/80 hover:border-purple-300 hover:shadow-xl hover:shadow-purple-100/50 transition-all duration-300"
                data-aos="fade-up" data-aos-delay="100">

                <!-- Icon + label -->
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-12 h-12 bg-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-purple-500/30">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold text-purple-600 uppercase tracking-wider bg-purple-100 px-2.5 py-1 rounded-md">
                        MCP Servers
                    </span>
                </div>

                <h3 class="text-2xl md:text-3xl font-display text-slate-900 mb-3">
                    Plug in any MCP server
                </h3>
                <p class="text-slate-600 leading-relaxed mb-6">
                    Connect Model Context Protocol servers to give your agent dozens of tools at once —
                    from GitHub and Slack to your own internal servers. Built on the open standard
                    used by Claude, Cursor, and beyond.
                </p>

                <!-- MCP connector mockup -->
                <div class="bg-white rounded-lg p-4 border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-md flex items-center justify-center text-white text-xs font-bold">
                                M
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-slate-900">github-mcp</div>
                                <div class="text-[10px] text-slate-500">https://mcp.example.com/github</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                            <span class="text-[10px] font-medium text-emerald-700">Connected</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <span class="text-[10px] px-2 py-0.5 bg-slate-100 text-slate-700 rounded font-mono">list_issues</span>
                        <span class="text-[10px] px-2 py-0.5 bg-slate-100 text-slate-700 rounded font-mono">create_pr</span>
                        <span class="text-[10px] px-2 py-0.5 bg-slate-100 text-slate-700 rounded font-mono">get_file</span>
                        <span class="text-[10px] px-2 py-0.5 bg-slate-100 text-slate-700 rounded font-mono">+ 12 more</span>
                    </div>
                </div>

                <!-- Features list -->
                <ul class="mt-6 space-y-2.5">
                    <li class="flex items-start gap-2.5 text-sm text-slate-700">
                        <svg class="w-5 h-5 text-purple-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span>Connect any remote MCP server in seconds</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-sm text-slate-700">
                        <svg class="w-5 h-5 text-purple-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span>Pick which tools each agent can use</span>
                    </li>
                    <li class="flex items-start gap-2.5 text-sm text-slate-700">
                        <svg class="w-5 h-5 text-purple-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span>Open standard — bring your own server</span>
                    </li>
                </ul>
            </div>

        </div>

    </div>
</section>
