<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A failure has to be answered twice over: once for a plain browser request and
 * once for an Inertia XHR. Both must keep the real status code, and neither may
 * show the person a stack trace or a bare HTML document.
 */
class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    /** The version the Inertia middleware will compare against, so a test request
     *  is not turned away with a 409 before it reaches the code under test. */
    private function inertiaVersion(): string
    {
        return (string) app(HandleInertiaRequests::class)->version(request());
    }

    public function test_a_missing_page_renders_the_branded_error_view(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('We could not find that page');
    }

    public function test_a_forbidden_page_renders_the_branded_error_view(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/users')
            ->assertForbidden()
            ->assertSee('That page is not yours to open');
    }

    public function test_the_error_view_does_not_depend_on_the_asset_build(): void
    {
        // The stylesheet is inlined, so the page still looks like the salon even
        // when the build directory is missing.
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('#0a3323', false)
            ->assertDontSee('@vite', false);
    }

    public function test_an_inertia_request_gets_the_error_component_not_html(): void
    {
        $this->actingAs(User::factory()->create())
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->inertiaVersion()])
            ->get('/admin/users')
            ->assertForbidden()
            // An Inertia XHR answers with the page object as JSON, not HTML, so
            // this reads the payload directly rather than through assertInertia,
            // which only inspects a rendered view.
            ->assertJsonPath('component', 'Error')
            ->assertJsonPath('props.status', 403);
    }

    public function test_the_error_response_keeps_its_status_code(): void
    {
        $this->actingAs(User::factory()->create())
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->inertiaVersion()])
            ->get('/no-such-page')
            ->assertNotFound();
    }

    public function test_with_debug_off_an_exception_leaks_nothing(): void
    {
        config(['app.debug' => false]);

        Route::middleware('web')->get('/boom', function () {
            throw new \RuntimeException('Database credentials rejected for user salon_admin');
        });

        $response = $this->get('/boom');

        $response->assertStatus(500)
            ->assertSee('Something went wrong at our end')
            // Neither the message nor the trace may reach the browser.
            ->assertDontSee('salon_admin')
            ->assertDontSee('RuntimeException')
            ->assertDontSee('vendor\laravel', false);
    }

    public function test_the_health_endpoint_reveals_no_internals(): void
    {
        $this->get('/up')
            ->assertOk()
            ->assertDontSee('APP_KEY')
            ->assertDontSee(config('database.connections.mysql.password') ?: 'no-password-set');
    }
}
