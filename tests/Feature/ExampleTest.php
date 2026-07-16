<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_url_serves_the_spa_shell(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_client_side_routes_serve_the_spa_shell(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/dashboard')->assertOk();
    }
}
