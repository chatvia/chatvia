### The conversation so far

The user typed these messages before the call started. Carry on from them.

@foreach ($messages as $message)
{{ $message['role'] === 'user' ? 'User' : 'You' }}: {!! $message['content'] !!}
@endforeach
