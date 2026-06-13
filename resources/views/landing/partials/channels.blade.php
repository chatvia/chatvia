@php
    // Messaging channels the agent can be deployed to — surfaces you REACH people
    // on, distinct from the connector marquee (tools the agent CALLS).
    $channels = [
        ['img' => 'images/logos/channels/telegram.svg',  'label' => 'Telegram'],
        ['img' => 'images/logos/channels/whatsapp.svg',  'label' => 'WhatsApp'],
        ['img' => 'images/logos/channels/slack.svg',     'label' => 'Slack'],
        ['img' => 'images/logos/channels/messenger.svg', 'label' => 'Messenger'],
        ['img' => 'images/logos/channels/msteams.svg',   'label' => 'Microsoft Teams'],
        ['img' => 'images/logos/channels/instagram.svg', 'label' => 'Instagram'],
    ];
@endphp

<!-- Channels — where the agent gets DEPLOYED (cards). Mirrors the integrations
     section's soft gradient + decorative glows for a consistent look. -->
<section class="relative overflow-hidden bg-gradient-to-b from-white via-indigo-50/30 to-white pt-10 pb-10">
    <!-- Soft decorative glows -->
    <div class="absolute inset-0 pointer-events-none">
        <div class="absolute top-1/3 left-1/4 w-72 h-72 bg-indigo-200/40 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 right-1/4 w-72 h-72 bg-purple-200/40 rounded-full blur-3xl"></div>
    </div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6">
        <div class="text-center max-w-2xl mx-auto">
            <span class="block text-sm font-bold uppercase tracking-widest text-indigo-600 mb-4">
                {{ __('landing.channels.eyebrow') }}
            </span>
            <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold text-slate-900 leading-tight tracking-tight">
                {{ __('landing.channels.title') }}
            </h2>
            <p class="mt-6 text-base sm:text-lg text-slate-600 leading-relaxed">
                {{ __('landing.channels.subtitle') }}
            </p>
        </div>

        <div class="mt-12 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 max-w-5xl mx-auto">
            @foreach ($channels as $channel)
                <div class="group flex flex-col items-center justify-center gap-3 bg-white border border-slate-200/80 shadow-sm px-4 py-6 text-center hover:border-indigo-200 hover:shadow-md transition-all duration-200">
                    <img src="{{ asset($channel['img']) }}" alt="{{ $channel['label'] }}" class="w-10 h-10 object-contain" />
                    <span class="text-sm font-semibold text-slate-700 group-hover:text-slate-900">{{ $channel['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>
</section>
