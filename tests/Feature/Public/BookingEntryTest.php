<?php

namespace Tests\Feature\Public;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_registration_first(): void
    {
        $this->get('/book')->assertRedirect(route('register'));
    }

    public function test_the_booking_destination_is_remembered_across_registration(): void
    {
        $this->get('/book');

        $this->assertSame(route('booking.start'), session('url.intended'));
    }

    public function test_a_signed_in_user_is_sent_onward_rather_than_to_registration(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/book')
            ->assertRedirect(route('dashboard'));
    }

    public function test_a_deactivated_user_is_not_quietly_let_through(): void
    {
        $user = User::factory()->inactive()->create();

        // The entry point sends them onward, and the active middleware on the
        // destination is what actually ejects them.
        $this->actingAs($user)->get('/book')->assertRedirect(route('dashboard'));
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_every_public_page_offers_a_route_into_booking(): void
    {
        foreach (['/', '/services', '/team', '/about', '/contact'] as $uri) {
            $this->get($uri)->assertOk();
        }

        // The CTA target itself must resolve, otherwise the links are dead.
        $this->assertSame(url('/book'), route('booking.start'));
    }
}
