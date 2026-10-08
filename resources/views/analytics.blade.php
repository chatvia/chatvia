{{-- Privacy-friendly page-view analytics (Plausible). Off unless a site
     domain is configured, so an install never reports to someone else's
     account. Production only, so local and test traffic is never counted. --}}
@if (app()->isProduction() && filled(config('services.plausible.domain')) && filled(config('services.plausible.script_url')))
    <script async data-domain="{{ config('services.plausible.domain') }}" src="{{ config('services.plausible.script_url') }}"></script>
    <script>
        window.plausible=window.plausible||function(){(plausible.q=plausible.q||[]).push(arguments)},plausible.init=plausible.init||function(i){plausible.o=i||{}};
        plausible.init()
    </script>
@endif
