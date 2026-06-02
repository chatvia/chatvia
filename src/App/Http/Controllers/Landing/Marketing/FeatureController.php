<?php

namespace App\Http\Controllers\Landing\Marketing;

use App\Features\Actions;
use App\Features\Widget;
use App\Features\Workflow;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;

class FeatureController
{
    public function __invoke(Request $request): array
    {
        return [
            'widget' => Feature::active(Widget::class),
            'workflow' => Feature::active(Workflow::class),
            'actions' => Feature::active(Actions::class),
        ];
    }
}
