<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) config('database.default') === 'mysql'
            ? (string) config('database.connections.mysql.database')
            : '';

        if ($database !== '' && ! str_ends_with($database, '_test')) {
            throw new \RuntimeException(
                'Tu choi chay test tren database that. Database test phai co duoi _test.'
            );
        }
    }
}
