<?php

use Domain\Channel\Services\ChannelReplyRunner;

/**
 * Forms render as a web-only inline artifact. The forms prompt is withheld off
 * the web, but as a backstop the channel runner also strips any stray
 * `<render-form …>` tag the model improvises as text so the raw tag never
 * reaches a text-only channel (Telegram, Slack, …).
 */
function stripArtifactTags(string $reply): string
{
    $runner = app(ChannelReplyRunner::class);
    $method = new ReflectionClass(ChannelReplyRunner::class)->getMethod('stripWebOnlyArtifactTags');

    return $method->invoke($runner, $reply);
}

it('strips a self-closing render-form tag and tidies the gap it leaves', function (): void {
    $reply = "Here are your details.\n\n<render-form slug=\"book-a-demo\"/>\n\nOur team will follow up.";

    expect(stripArtifactTags($reply))
        ->toBe("Here are your details.\n\nOur team will follow up.");
});

it('strips a paired render-form tag with inner content', function (): void {
    $reply = 'Please fill this: <render-form slug="lead-capture">fallback</render-form> thanks!';

    expect(stripArtifactTags($reply))->toBe('Please fill this:  thanks!');
});

it('leaves ordinary replies untouched', function (): void {
    $reply = 'Sure — could you share your name and email so the team can follow up?';

    expect(stripArtifactTags($reply))->toBe($reply);
});
