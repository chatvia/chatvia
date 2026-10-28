QUESTION ASKED OF THE AGENT:
{!! $question !!}

GRADING CRITERIA (what a correct answer must satisfy):
{!! $criteria !!}
@if ($reference !== '')

REFERENCE ANSWER (a known-good answer; the agent need not match it word-for-word):
{!! $reference !!}
@endif

AGENT'S ANSWER:
{!! $answer !!}
