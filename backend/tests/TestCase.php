<?php

namespace Tests;

use App\Models\User;
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

    /**
     * Acts as the given user for the following requests, even if the test acted as someone else before.
     *
     * Sanctum's request guard caches the first user it resolves for the lifetime of the application, so a
     * plain actingAs() after another one may keep acting as the first user. Guards are reset first, and the
     * identity the API will see is asserted, so a switch that did not happen fails loudly.
     */
    public function actAs(User $user): static
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs($user);

        $this->assertSame($user->getKey(), $this->app['auth']->guard('sanctum')->user()?->getKey(), 'The test did not switch to the intended actor.');

        return $this;
    }

    /**
     * Drops any authenticated actor so the following requests are made as a guest.
     */
    public function actAsGuest(): static
    {
        $this->app['auth']->forgetGuards();

        $this->assertNull($this->app['auth']->guard('sanctum')->user(), 'The test is still acting as a user.');

        return $this;
    }
}
