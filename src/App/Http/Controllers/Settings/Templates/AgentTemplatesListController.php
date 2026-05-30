<?php

namespace App\Http\Controllers\Settings\Templates;

use App\Http\Controllers\Controller;
use Domain\Agents\Actions\ListTemplatesForPickerAction;
use Domain\Agents\Data\Resources\AgentTemplatePickerListData;
use Illuminate\Http\Request;

/**
 * JSON list of the user's templates — used by the agent setup banner's
 * "attach a template" picker.
 */
class AgentTemplatesListController extends Controller
{
    public function __invoke(Request $request, ListTemplatesForPickerAction $list): AgentTemplatePickerListData
    {
        abort_unless(has_team_permission('account.templates'), 404);

        return $list->execute($request->user());
    }
}
