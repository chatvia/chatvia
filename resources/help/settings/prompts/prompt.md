A prompt is the text the app sends to an AI model to tell it what to do. The text you see is the one in use: the default, or your own version.

- **Change it** — Edit the text and save the page. Only prompts that differ from their default are saved. The change applies at once, also to background jobs.
- **Variables** — Words like `{!! $question !!}` are filled in by the app each time, for example with the visitor's question. Use only the variables listed above the text. A prompt that has none takes no variables.
- **Conditions and lists** — You can use `@if ($name) … @endif`, `@unless`, `@else`, and `@foreach ($items as $item) … @endforeach`, as in the default. Other Blade, PHP and function calls are not allowed, so a saved prompt can never run code on the server.
- **Restore default** — Removes your version at once. The app then uses the default again, and follows it when an update changes it.

**Tip:** Some prompts ask the model for an exact answer format, such as "language|question" for {title}. Keep that format, or the step that reads the answer cannot use it.
