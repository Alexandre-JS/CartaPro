<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_the_public_website(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertViewIs('website.home')
            ->assertSee('Prepare-se para conduzir com confiança.')
            ->assertSee('ProntoVia');
    }
}
