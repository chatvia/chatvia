### User Question:
{!! $question !!}

### Reference material
Nothing in the knowledge matches this message.
@if ($clarifyingQuestion)

The message is too vague to look up. Do not say that you have no information. Ask the visitor what they mean, for example: "{!! $clarifyingQuestion !!}"
@elseif ($scope !== [])

If you cannot answer it with your tools either, say so briefly, then tell the visitor what you can help with. Your knowledge covers:
@foreach ($scope as $name)
- {!! $name !!}
@endforeach
@endif
