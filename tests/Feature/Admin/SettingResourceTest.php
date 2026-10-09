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

    $response->assertSee(SettingGroup::Meta->label())
        ->assertSee(SettingGroup::Stripe->label())
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
    $setting = AdminSetting::forGroup(SettingGroup::Meta);
    $setting->update(['payload' => ['app_id' => '123', 'app_secret' => 'secret']]);

    Livewire::actingAs($this->admin, 'admin')
        ->test(EditSetting::class, ['record' => SettingGroup::Meta->value])
        ->callAction('removeSavedValues');

    expect($setting->refresh()->payload)->toBeNull();
});
