<?php

if (app()->isLocal()) {
    Route::get('playground', function () {
        $len = 14;

        return substr(bin2hex(random_bytes((int) ceil($len / 2))), 0, $len);

    });
}
