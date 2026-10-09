<?php

namespace App\Http\Requests\Agent\Channel;

use Domain\Channel\Teams\Data\ConnectTeamsData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Connect an Azure bot to an agent: the bot's Microsoft App ID (a GUID) and a
 * client secret from its app registration, plus the Entra tenant ID for
 * single-tenant bots (multi-tenant bots leave it empty). Authorization is the
 * route's `agent.permission:agent.channels` middleware.
 */
class ConnectTeamsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'app_id' => ['required', 'uuid'],
            'app_secret' => ['required', 'string', 'min:10', 'max:128'],
            'tenant_id' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'app_id.uuid' => __('That doesn’t look like a Microsoft App ID — it’s the GUID on your Azure bot’s Configuration page.'),
            'tenant_id.uuid' => __('That doesn’t look like a tenant ID — it’s the Directory (tenant) ID GUID in your app registration. Leave it empty for a multi-tenant bot.'),
        ];
    }

    public function toData(): ConnectTeamsData
    {
        return new ConnectTeamsData(
            appId: $this->string('app_id')->toString(),
            appSecret: $this->string('app_secret')->toString(),
            tenantId: $this->filled('tenant_id') ? $this->string('tenant_id')->toString() : null,
        );
    }
}
