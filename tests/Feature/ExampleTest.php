<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_rota_login_esta_registrada(): void
    {
        $this->assertTrue(
            collect(\Route::getRoutes()->getRoutesByName())
                ->has('login'),
            'A rota "login" deve estar registrada'
        );
    }
}
