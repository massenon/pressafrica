<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Views are tested without a Vite build (no Node on the host; assets are built before deploy).
        $this->withoutVite();
    }
}
