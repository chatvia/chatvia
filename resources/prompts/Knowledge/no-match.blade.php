### User Question:
{!! $question !!}

### Reference material
Nothing in the knowledge matches this message.
@if ($clarifyingQuestion)

The message is too vague to look up. Do not say that you have no information. Ask the visitor what they mean, for example: "{!! $clarifyingQuestion !!}"
@elseif ($scope !== [])

If one of your tools can answer it, call the tool right away and don't say first that you have no information. If your tools cannot answer it either, say so briefly, then tell the visitor what you can help with. Your knowledge covers:
@foreach ($scope as $name)
- {!! $name !!}
@endforeach
@endif
