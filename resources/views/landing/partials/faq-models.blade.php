{{-- Supported models, pulled live from the LLM catalog (passed as $models by HomeController / PricingController). --}}
<p class="mb-4">You can choose the best model for each agent and switch anytime. We currently support:</p>
<div class="flex flex-wrap gap-2">
    @foreach (($models ?? []) as $model)
        <span class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700">
            @if (!empty($model['icon']))
                <img src="{{ asset('images/llms/' . $model['icon']) }}" alt="{{ $model['name'] }}" class="h-4 w-4 flex-shrink-0" />
            @endif
            {{ $model['name'] }}
        </span>
    @endforeach
</div>
