<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changelog - ChatVia</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900">
<div class="min-h-screen">

<div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Changelog</h1>
            <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                Track the latest updates, improvements, and new features in ChatVia
            </p>
        </div>

        <!-- Improvement / Feedback Note -->
        <div class="mb-8">
            <div class="flex items-start gap-3 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-lg p-4 ml-16">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 mt-0.5">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.993.883L9 8v4a1 1 0 001.993.117L11 12V8a1 1 0 00-1-1zm0 8a1.25 1.25 0 100-2.5A1.25 1.25 0 0010 15z" clip-rule="evenodd" />
                </svg>
                <p class="text-sm leading-relaxed">
                    We're constantly improving ChatVia. If you want to report a bug or request a feature,
                    you can do it via your <a href="{{ route('dashboard') }}" class="font-medium underline hover:no-underline text-emerald-700">dashboard</a>.
                </p>
            </div>
        </div>

        <!-- Changelog Timeline -->
        <div class="space-y-8">
            @foreach($changelogs as $index => $changelog)
            <div class="relative">
                <div class="absolute left-8 top-0 w-4 h-4 {{ $index === 0 ? 'bg-blue-500' : 'bg-gray-400' }} rounded-full border-4 border-white shadow-lg"></div>
                <div class="ml-16">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow duration-200">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-2xl font-bold text-gray-900">Version {{ $changelog->version }}</h2>
                        </div>
                        <p class="text-gray-600 mb-4">Released on {{ $changelog->release_date->format('M d, Y') }}</p>

                        <div class="space-y-4">
                            @php
                                $groupedItems = $changelog->items->groupBy('type');
                            @endphp
                            @foreach($groupedItems as $changeType => $items)
                                @if(isset($changeTypes[$changeType]))
                                    @php
                                        $changeTypeConfig = $changeTypes[$changeType];
                                    @endphp
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800 mb-2 flex items-center">
                                            <span class="w-2 h-2 bg-{{ $changeTypeConfig['color'] }}-500 rounded-full mr-3"></span>
                                            {{ $changeTypeConfig['label'] }}
                                        </h3>
                                        <ul class="space-y-2 text-gray-700">
                                            @foreach($items as $item)
                                                <li class="flex items-start">
                                                    <span class="text-{{ $changeTypeConfig['color'] }}-500 mr-2">{{ $changeTypeConfig['icon'] }}</span>
                                                    {{ $item->description }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Footer -->
        <div class="text-center mt-16 pt-8 border-t border-gray-200">
            <p class="text-gray-500">

                <a href="{{ route('register') }}" class="text-blue-600 hover:text-blue-800 font-medium">Signup now</a>

            </p>
        </div>
    </div>
</div>
</div>
</body>
</html>
