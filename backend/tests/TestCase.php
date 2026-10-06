<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Several streams of work may run the suite at the same time, each in its own database: set TEST_DB_DATABASE
     * (the database has to exist, see docker/mysql/init). Without it the usual bookshelf_testing is used.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        if ($database = getenv('TEST_DB_DATABASE')) {
            $app['config']->set('database.connections.mysql.database', $database);
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // No test may ever reach a real external service: every HTTP call has to be faked explicitly.
        Http::preventStrayRequests();
    }
}
