<?php

namespace App\Http\Controllers\Widget;

use Illuminate\Http\Request;

class WidgetThreadsStoreController
{
    public function __invoke(Request $request)
    {
        return ['id' => 1];
    }
}
