<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_the_home_route_renders_the_public_home_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Public/Home')
                ->has('categories')
                ->has('staff')
                ->has('gallery')
        );
    }

    public function test_inertia_shares_the_application_name_on_every_response(): void
    {
        $response = $this->get('/');

        $response->assertInertia(
            fn (Assert $page) => $page->where('name', config('app.name'))
        );
    }

    public function test_shared_props_do_not_leak_unexpected_keys(): void
    {
        $response = $this->get('/');

        // Shared data is sent on every response, so its shape is deliberately
        // pinned here. Adding a key must be a conscious decision, not a drift.
        $response->assertInertia(
            fn (Assert $page) => $page->hasAll(['name', 'auth', 'flash'])
                ->has('flash', fn (Assert $flash) => $flash->hasAll(['success', 'error']))
        );
    }

    public function test_the_health_check_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }
}
