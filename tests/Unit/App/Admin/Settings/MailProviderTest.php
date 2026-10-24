<?php

use App\Admin\Settings\MailProvider;
use App\Admin\Settings\MailSettings;

/**
 * Each mailer Settings → Email offers must work once picked: Laravel has a
 * mail.mailers entry with its transport, and each field it asks for is a
 * saved setting.
 */
it('gives every mailer a mail.mailers entry and a setting for each of its fields', function (MailProvider $provider): void {
    expect(config("mail.mailers.{$provider->value}.transport"))->toBe($provider->value);

    foreach ($provider->fields() as $field) {
        expect(MailSettings::configKeys())->toHaveKey($field->property);
    }
})->with(MailProvider::cases());
