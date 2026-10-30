@foreach ($passages as $number => $text)
<passage number="{{ $number }}">
{!! $text !!}
</passage>
@endforeach
