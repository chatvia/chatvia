<?php

namespace App\Http\Controllers;

use App\Features\Widget;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;

class FeaturesController
{
    public function __invoke(Request $request)
    {
        return [
            'widget' => Feature::active(Widget::class),
        ];
    }
}
