<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_up_reports_healthy_when_the_database_is_reachable(): void
    {
        $response = $this->getJson('/up');

        $response->assertOk();
        $response->assertJson(['status' => 'up']);
    }

    public function test_up_reports_unhealthy_when_the_database_is_unreachable(): void
    {
        // In debug mode Laravel would rethrow the exception instead of turning
        // it into the down/500 response; production runs with debug off, so
        // match that here to exercise the actual behavior users see.
        Config::set('app.debug', false);

        // Point the default connection at a driver/host that cannot be reached,
        // simulating the database being down without needing a real outage.
        Config::set('database.default', 'pgsql');
        Config::set('database.connections.pgsql.host', 'db-does-not-exist.invalid');
        Config::set('database.connections.pgsql.port', 1);

        $response = $this->getJson('/up');

        $response->assertServerError();
        $response->assertJson(['status' => 'down']);
    }
}
