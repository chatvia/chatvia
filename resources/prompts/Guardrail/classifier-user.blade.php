@if ($checkTopic)
ALLOWED SCOPE (answer only within this): {!! $allowedScope !!}
BLOCKED TOPICS (always off-topic):
{!! $blockedTopics !!}

@endif
@if ($conversation !== '')
<conversation>
{!! $conversation !!}
</conversation>

@endif
<text>
{!! $text !!}
</text>
