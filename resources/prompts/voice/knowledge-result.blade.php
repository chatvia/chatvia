@if ($answer !== null)
A prepared answer to this question. Say it in your own words, in the
user's language:

{!! $answer !!}
@elseif ($items === [])
Nothing in the knowledge matches "{!! $query !!}". Say briefly that you
don't have that information, and offer the closest useful next step.
@else
What the knowledge says about "{!! $query !!}", best match first. Answer
only from this, briefly, in the user's language, and never say the source
numbers:
@foreach ($items as $item)

<source id="{{ $item['id'] }}">
{!! $item['content'] !!}
</source>
@endforeach
@endif
