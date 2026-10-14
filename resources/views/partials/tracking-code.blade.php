{{-- The tracking code an admin sets (Settings → Platform), such as the
     script of an analytics service. Off while it is empty, so an install
     never reports to someone else's account. Production only, so local and
     test traffic is never counted. Printed as it is: only a platform admin
     can set it. --}}
@if (app()->isProduction() && filled(config('chatvia.tracking_code')))
    {!! config('chatvia.tracking_code') !!}
@endif
