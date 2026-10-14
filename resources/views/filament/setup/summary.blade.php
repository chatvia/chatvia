{{-- A list of names and values on the setup's last step: the choices made, or where the defaults are changed. --}}
<dl class="cv-setup-summary">
    @foreach ($rows as $name => $value)
        <div class="cv-setup-summary__row">
            <dt>{{ $name }}</dt>
            <dd>{{ $value }}</dd>
        </div>
    @endforeach
</dl>
