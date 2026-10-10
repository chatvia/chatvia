<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Billing On / Off, Sign-up, Trial, Currency and Discount
    |--------------------------------------------------------------------------
    |
    | These are set in the admin panel (Settings → Billing), not here. When
    | the app boots, App\Admin\Settings\SettingsStore puts the saved values
    | into these keys, or the panel's defaults while nothing is saved. The
    | nulls in this file are only placeholders.
    |
    | `enabled` turns paid plans on or off. With paid plans on, people sign
    | up through the Stripe trial checkout, and the plan decides features,
    | quotas and message credits. With them off (the default of a new
    | install) every workspace gets every feature, quotas have no limit,
    | messages are not counted against credits, the billing pages answer 404,
    | and no Stripe keys are needed.
    |
    | `registration_enabled` opens the normal sign-up form at /register. By
    | default it is on only while paid plans are off.
    |
    | `unlimited_retention_days` is how many days conversations are kept while
    | paid plans are off. With them on, each plan sets its own value.
    |
    */

    'enabled' => null,

    'registration_enabled' => null,

    'unlimited_retention_days' => null,

    'discount' => [
        'percentage' => null,
        'ends_at' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Plans and Add-ons
    |--------------------------------------------------------------------------
    |
    | Plans and add-ons (prices, Stripe price ids, limits, features) live in
    | the database (`billing_plans`, `billing_addons`) and are managed in the
    | admin panel. Only the auto-recharge daily cap has a key here: the most
    | off-session auto-recharges per workspace in a rolling 24 hours. The hourly
    | debounce alone permits ~24 card charges a day; this caps a runaway spend
    | spike (for example anonymous public-widget traffic). Its value is set in
    | the admin panel (Settings → Billing), like the keys above.
    |
    */
    'addons' => [
        'auto_recharge_credits' => [
            'max_per_day' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial and Currency
    |--------------------------------------------------------------------------
    |
    | Also set in the admin panel (Settings → Billing). The currency symbol is
    | not set anywhere: it comes from the currency (€ for eur).
    |
    */
    'trial' => [
        'days' => null,
    ],

    'currency' => null,
    'currency_symbol' => null,
];
