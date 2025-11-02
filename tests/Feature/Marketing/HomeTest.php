<?php

it('returns a successful response for home page', function () {
    $response = $this->get(route('home'));

    $response->assertStatus(200);
});

it('returns a successful response for blog', function () {
    $response = $this->get(route('blog'));

    $response->assertStatus(200);
});
