Passages the agent was given:
@foreach ($blocks as $index => $block)

<passage id="{{ $index + 1 }}">
{!! $block !!}
</passage>
@endforeach

Sentences to check, with the passages each cites:
@foreach ($claims as $index => $claim)
{{ $index + 1 }}. {!! $claim['sentence'] !!} (cites {{ implode(', ', $claim['blocks']) }})
@endforeach
