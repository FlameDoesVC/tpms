<?php

namespace Tests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Roles must exist for registration; seeded on every database refresh.
    protected $seed = true;

    protected $seeder = RoleSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Frontend assets are not built in the test environment.
        $this->withoutVite();
    }
}
