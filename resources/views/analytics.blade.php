@if (app()->isProduction())
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-RTCBL1ZT5C"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', 'G-RTCBL1ZT5C');
    </script>
@endif
