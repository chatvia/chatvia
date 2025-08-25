<?php

namespace App\Http\Controllers\Widget;

use App\Services\Widget\WidgetVerification;
use Illuminate\Http\Request;

class WidgetSettingsController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification)
    {
        if (! $widgetVerification->verifyByAgentExternalId($request->header('x-agent-id'), $request)) {
            return response()->json(['error' => 'Could not verify the agent'], 403);
        }

        $agent = current_agent_from_widget();

        $widgetSettings = $agent->settings('widget');

        if (! $widgetSettings['enabled']) {
            return response()->json(['error' => 'Widget is disabled'], 403);
        }

        $appearanceSettings = $agent->settings('appearance');

        $starters = $agent->starters()->pluck('text')->toArray();

        return [
            'enabled' => $widgetSettings['enabled'],
            'title' => $appearanceSettings['chat_window_title'],
            'logo_url' => '',
            'welcome_message' => $appearanceSettings['welcome_message'],
            'primary_color' => $widgetSettings['primary_color'],
            'theme' => $widgetSettings['theme'],
            'show_theme_switcher' => $widgetSettings['show_theme_switcher'],
            'position' => $widgetSettings['position'],
            'show_on_load' => $widgetSettings['show_on_load'],
            'show_on_load_delay' => $widgetSettings['show_on_load_delay'],
            'branding' => [
                'enabled' => true,
                'name' => 'Powered by ChatVia.ai',
                'url' => 'https://chatvia.ai',
                'logo_url' => 'https://chatvia.ai/images/logo.svg',
            ],
            'starters' => $starters,
            'window_size' => $widgetSettings['window_size'],
        ];
    }
}
