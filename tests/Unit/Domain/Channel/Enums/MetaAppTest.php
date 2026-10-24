<?php

declare(strict_types=1);

use Domain\Channel\Enums\MetaApp;

/**
 * A Meta app counts as set up once an admin filled in every field the admin
 * panel requires (Settings → Channels). WhatsApp's Embedded Signup also needs
 * its Login configuration; Messenger and Instagram do without one.
 */
function maConfigure(MetaApp $app): void
{
    config([
        "services.meta.{$app->value}.app_id" => 'APP-ID',
        "services.meta.{$app->value}.app_secret" => 'APP-SECRET',
        "services.meta.{$app->value}.webhook_verify_token" => 'VERIFY-TOKEN',
        "services.meta.{$app->value}.config_id" => 'CONFIG-ID',
    ]);
}

it('counts an app as set up once every required field is filled', function (MetaApp $app): void {
    maConfigure($app);

    expect($app->isConfigured())->toBeTrue();
})->with(MetaApp::cases());

it('does not count an app as set up while a required field is blank', function (MetaApp $app, string $field): void {
    maConfigure($app);
    config(["services.meta.{$app->value}.{$field}" => '']);

    expect($app->isConfigured())->toBeFalse();
})->with([
    'Instagram without a verify token' => [MetaApp::Instagram, 'webhook_verify_token'],
    'Messenger without an app secret' => [MetaApp::Messenger, 'app_secret'],
    'WhatsApp without an app ID' => [MetaApp::Whatsapp, 'app_id'],
    'WhatsApp without its Login configuration' => [MetaApp::Whatsapp, 'config_id'],
]);

it('needs no Login configuration for Messenger or Instagram', function (MetaApp $app): void {
    maConfigure($app);
    config(["services.meta.{$app->value}.config_id" => null]);

    expect($app->isConfigured())->toBeTrue();
})->with([MetaApp::Messenger, MetaApp::Instagram]);
