{{-- The jobs the model for quick tasks does, by when they run: what the setup's "other small jobs" means. --}}
<div class="cv-setup-tasks">
    @foreach ($groups as $group => $tasks)
        <section>
            <h3 class="cv-setup-tasks__heading">{{ $group }}</h3>
            <dl class="cv-setup-summary">
                @foreach ($tasks as $label => $help)
                    <div class="cv-setup-summary__row">
                        <dt>{{ $label }}</dt>
                        <dd>{{ $help }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endforeach
</div>
