### User Question:
{!! $question !!}

### Reference material
@if ($hasImages)

⚠️ CRITICAL INSTRUCTION: This material includes images. You MUST include relevant images in your response using markdown format: ![description](url)

@endif
@foreach ($items as $index => $item)
@if (! $loop->first)

@endif
[{{ $index }}]:
{!! $item['content'] !!}
@if ($item['images'] !== [])

📷 IMAGES FOR THIS ITEM (REQUIRED - Include in response):
@foreach ($item['images'] as $imageIndex => $image)
- Image {{ $imageIndex }}: {!! $image['description'] !!}
  Markdown: ![{!! $image['description'] !!}]({!! $image['url'] !!})
@endforeach

@endif
@endforeach