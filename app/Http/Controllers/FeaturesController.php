<?php

namespace App\Http\Controllers;

use App\Features\Actions;
use App\Features\Widget;
use App\Features\Workflow;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;

class FeaturesController
{
    public function __invoke(Request $request)
    {
        return [
            'widget' => Feature::active(Widget::class),
            'workflow' => Feature::active(Workflow::class),
            'actions' => Feature::active(Actions::class),
        ];
    }
}
