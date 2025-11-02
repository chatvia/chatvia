<?php

namespace Tests;

use Faker\Factory;
use Faker\Generator;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function faker(): Generator
    {
        return Factory::create();
    }
}
