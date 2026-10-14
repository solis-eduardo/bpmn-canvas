<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\Share;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\VersionRepository;
use App\Support\Demo;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Tags\Tag;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    private function demoOn(): void
    {
        config(['bpmn.demo_mode' => true, 'bpmn.version_store' => 'database']);
    }

    public function test_demo_affordances_do_not_render_when_demo_mode_is_off(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('data-demo-banner', false)
            ->assertDontSee(route('demo.sign-in', 'author'), false);

        $user = User::factory()->create();
        $this->actingAs($user)->get('/home')->assertDontSee('data-demo-banner', false);
    }

    public function test_demo_routes_are_not_found_when_demo_mode_is_off(): void
    {
        $this->post(route('demo.sign-in', 'author'))->assertNotFound();
        $this->post(route('demo.reset'))->assertNotFound();
    }

    public function test_login_lists_demo_accounts_and_banner_when_demo_mode_is_on(): void
    {
        $this->demoOn();

        $this->get('/login')
            ->assertOk()
            ->assertSee('data-demo-banner', false)
            ->assertSee(__('messages.demo_banner'))
            ->assertSee(route('demo.sign-in', 'administrator'), false)
            ->assertSee(route('demo.sign-in', 'collaborator'), false);
    }

    public function test_demo_login_hides_the_keycloak_button(): void
    {
        $this->demoOn();
        config(['services.keycloak.client_id' => 'x', 'services.keycloak.client_secret' => 'y', 'services.keycloak.base_url' => 'http://kc']);

        $this->get('/login')->assertDontSee(route('login.keycloak'), false);
    }

    public function test_sign_in_as_a_demo_account(): void
    {
        $this->demoOn();
        $this->seed(DemoSeeder::class);

        $this->post(route('demo.sign-in', 'author'))->assertRedirect(route('home'));

        $this->assertAuthenticatedAs(User::where('email', Demo::ACCOUNTS['author']['email'])->first());
    }

    public function test_unknown_demo_account_is_not_found(): void
    {
        $this->demoOn();

        $this->post(route('demo.sign-in', 'nobody'))->assertNotFound();
    }

    public function test_demo_account_cannot_change_its_password(): void
    {
        $this->demoOn();
        $this->seed(DemoSeeder::class);
        $user = User::where('email', Demo::ACCOUNTS['author']['email'])->first();

        $this->actingAs($user)->get('/account')->assertDontSee('name="current_password"', false);
        $this->actingAs($user)->put('/account/password', [
            'current_password' => Demo::PASSWORD,
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])->assertForbidden();
    }

    public function test_reset_reseeds_the_database_and_signs_out(): void
    {
        $this->demoOn();
        $this->seed(DemoSeeder::class);
        $user = User::where('email', Demo::ACCOUNTS['author']['email'])->first();

        // Wiping the schema cannot run inside the test's transaction.
        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate:fresh', ['--seeder' => DemoSeeder::class, '--force' => true]);

        $this->actingAs($user)->post(route('demo.reset'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_seeder_covers_every_listed_feature(): void
    {
        $this->demoOn();
        $this->seed(DemoSeeder::class);

        $this->assertSame(3, User::whereIn('email', array_column(Demo::ACCOUNTS, 'email'))->count());
        $this->assertSame(1, User::where('is_admin', true)->count());
        $this->assertGreaterThan(0, Diagram::whereNotNull('locked_by')->count());
        $this->assertGreaterThan(0, Collaborator::where('status', 'requested')->count());
        $this->assertGreaterThan(0, Collaborator::where('status', 'approved')->count());
        $this->assertGreaterThan(0, Share::active()->count());
        $this->assertGreaterThan(0, Workspace::count());
        $this->assertGreaterThan(0, Tag::count());

        $order = Diagram::where('title', 'Order fulfilment')->first();
        $versions = app(VersionRepository::class)->list($order);
        $this->assertGreaterThanOrEqual(5, count($versions));
        $this->assertTrue(collect($versions)->contains('renamed', true));
        $this->assertTrue(collect($versions)->contains('provisional', true));
    }
}
