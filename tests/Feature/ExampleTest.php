<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_home_page_opens_the_radar(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/radar');
    }
}
