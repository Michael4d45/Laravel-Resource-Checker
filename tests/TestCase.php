<?php

declare(strict_types=1);

namespace Tests;

use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            'Michael4d45\LaravelResourceChecker\Providers\LaravelResourceCheckerServiceProvider',
        ];
    }
}
