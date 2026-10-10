<?php

use App\Admin\Resources\Settings\Pages\EditSetting;
use App\Admin\Resources\Settings\SettingResource;
use App\Admin\Settings\AdminSetting;
use App\Admin\Settings\SettingCategory;
use App\Admin\Settings\SettingGroup;
use App\Identity\Workspace\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * The Settings resource of the admin panel: a card per settings group and an
 * edit page per group, where saved secrets are write-only.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
    $this->admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
});

it('shows a card for every settings group', function (): void {
    $response = $this->actingAs($this->admin, 'admin')
        ->get(SettingResource::getUrl('index'))
        ->assertOk();

    foreach (SettingCategory::cases() as $category) {
        $response->assertSee($category->label());
    }

    $response->assertSee(SettingGroup::Instagram->label())
        ->assertSee(SettingGroup::Whatsapp->label())
        ->assertSee(SettingGroup::Stripe->label())
        ->assertSee(SettingGroup::Billing->description())
        ->assertSee(SettingResource::getUrl('edit', ['record' => SettingGroup::GoogleLogin->value]));
});

it('locks the credentials for admins without two-factor authentication', function (): void {
    $withoutMfa = User::factory()->admin()->create()->fresh();
    AdminSetting::forGroup(SettingGroup::Stripe)->update(['payload' => ['key' => 'pk_test_hidden']]);

    $this->actingAs($withoutMfa, 'admin')
        ->get(SettingResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Set up two-factor authentication first')
        ->assertDontSee(SettingGroup::Stripe->label());

    $this->get(SettingResource::getUrl('edit', ['record' => SettingGroup::Stripe->value]))
        ->assertForbidden();

    expect(SettingResource::canManage())->toBeFalse();

    $this->actingAs($this->admin, 'admin');
    expect(SettingResource::canManage())->toBeTrue();
});

it('answers 404 for an unknown group', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get('/admin/settings/not-a-group/edit')
        ->assertNotFound();
});

it('never sends a saved secret to the browser', function (): void {
    AdminSetting::forGroup(SettingGroup::GoogleLogin)
        ->update(['payload' => ['client_id' => 'visible-id', 'client_secret' => 'top-secret-value']]);

    Livewire::actingAs($this->admin, 'admin')
        ->test(EditSetting::class, ['record' => SettingGroup::GoogleLogin->value])
        ->assertOk()
        ->assertSchemaStateSet(['payload.client_id' => 'visible-id', 'payload.client_secret' => null])
        ->assertDontSee('top-secret-value');
});

it('keeps a saved secret when its input is left blank', function (): void {
    $setting = AdminSetting::forGroup(SettingGroup::GoogleLogin);
    $setting->update(['payload' => ['client_id' => 'old-id', 'client_secret' => 'kept-secret']]);

    Livewire::actingAs($this->admin, 'admin')
        ->test(EditSetting::class, ['record' => SettingGroup::GoogleLogin->value])
        ->fillForm(['payload.client_id' => 'new-id', 'payload.client_secret' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    $setting->refresh();

    expect($setting->payload)->toBe(['client_id' => 'new-id', 'client_secret' => 'kept-secret'])
        ->and($setting->updated_by)->toBe($this->admin->id);
});

it('creates the row of a group on first save', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(EditSetting::class, ['record' => SettingGroup::Scaleway->value])
        ->fillForm(['payload.api_key' => 'scw-key'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(AdminSetting::forGroup(SettingGroup::Scaleway)->payload)->toBe(['api_key' => 'scw-key']);
});

it('validates the format of a field', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(EditSetting::class, ['record' => SettingGroup::Stripe->value])
        ->fillForm(['payload.key' => 'not-a-publishable-key'])
        ->call('save')
        ->assertHasFormErrors(['payload.key']);

    expect(AdminSetting::forGroup(SettingGroup::Stripe)->payload)->toBeNull();
});

it('removes one saved secret', function (): void {
    $setting = AdminSetting::forGroup(SettingGroup::GoogleLogin);
    $setting->update(['payload' => ['client_id' => 'id', 'client_secret' => 'secret']]);

    Livewire::actingAs($this->admin, 'admin')
        ->test(EditSetting::class, ['record' => SettingGroup::GoogleLogin->value])
        ->callAction(TestAction::make('forget')->schemaComponent('payload.client_secret', schema: 'form'));

    expect($setting->refresh()->payload)->toBe(['client_id' => 'id']);
});

it('removes every saved value of a group', function (): void {
    $setting = AdminSetting::forGroup(SettingGroup::Messenger);
    $setting->update(['payload' => ['app_id' => '123', 'app_secret' => 'secret']]);

    Livewire::actingAs($this->admin, 'admin')
        ->test(EditSetting::class, ['record' => SettingGroup::Messenger->value])
        ->callAction('removeSavedValues');

    expect($setting->refresh()->payload)->toBeNull();
});

it('shows the billing values in use, with the warning about Stripe subscriptions', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(EditSetting::class, ['record' => SettingGroup::Billing->value])
        ->assertOk()
        ->assertSchemaStateSet([
            'payload.enabled' => false,
            'payload.registration_enabled' => true,
            'payload.trial_days' => 7,
            'payload.currency' => 'eur',
            'payload.unlimited_retention_days' => 366,
            'payload.discount_percentage' => 0,
            'payload.discount_ends_at' => null,
            'payload.auto_recharge_max_per_day' => 3,
        ])
        ->assertSee('It does not cancel subscriptions in Stripe.');
});

it('saves the billing values as strings, an off switch included', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(EditSetting::class, ['record' => SettingGroup::Billing->value])
        ->fillForm([
            'payload.enabled' => true,
            'payload.registration_enabled' => false,
            'payload.trial_days' => 14,
            'payload.currency' => 'usd',
            'payload.discount_percentage' => 10,
            'payload.discount_ends_at' => '2026-12-31',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(AdminSetting::forGroup(SettingGroup::Billing)->payload)->toBe([
        'enabled' => '1',
        'registration_enabled' => '0',
        'trial_days' => '14',
        'currency' => 'usd',
        'unlimited_retention_days' => '366',
        'discount_percentage' => '10',
        'discount_ends_at' => '2026-12-31',
        'auto_recharge_max_per_day' => '3',
    ]);
});

it('shows saved billing values back in the form', function (): void {
    AdminSetting::forGroup(SettingGroup::Billing)->update(['payload' => ['enabled' => '1', 'trial_days' => '30', 'currency' => 'gbp']]);

    Livewire::actingAs($this->admin, 'admin')
        ->test(EditSetting::class, ['record' => SettingGroup::Billing->value])
        ->assertSchemaStateSet([
            'payload.enabled' => true,
            'payload.registration_enabled' => false,
            'payload.trial_days' => 30,
            'payload.currency' => 'gbp',
        ]);
});

it('validates the billing values', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(EditSetting::class, ['record' => SettingGroup::Billing->value])
        ->fillForm(['payload.discount_percentage' => 150, 'payload.trial_days' => -1, 'payload.currency' => 'xyz'])
        ->call('save')
        ->assertHasFormErrors(['payload.discount_percentage', 'payload.trial_days', 'payload.currency']);

    expect(AdminSetting::forGroup(SettingGroup::Billing)->payload)->toBeNull();
});
