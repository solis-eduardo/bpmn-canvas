<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Diagram;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_home(): void
    {
        $this->get('/home')->assertRedirect('/login');
    }

    public function test_home_greets_the_user_and_offers_dashboard_shortcuts(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);

        $response = $this->actingAs($user)->get('/home');

        $response->assertOk()
            ->assertSee(__('messages.home_greeting', ['name' => $user->name]))
            ->assertSee(__('messages.create_diagram'))
            ->assertSee(__('messages.home_workspace_shortcut'))
            ->assertSee(__('messages.home_notifications_shortcut'))
            ->assertSee(__('messages.home_search_shortcut'))
            ->assertSee(route('diagrams.create'), false)
            ->assertSee(route('workspaces.index'), false)
            ->assertSee(route('notifications.history'), false)
            ->assertSee(route('diagrams.index'), false)
            ->assertSee('href="'.url('/home').'"', false);
    }

    public function test_home_shows_the_five_most_recent_diagrams_the_user_can_open(): void
    {
        $user = User::factory()->create();
        $visible = [];

        for ($index = 1; $index <= 6; $index++) {
            $diagram = Diagram::factory()->for($user, 'author')->create([
                'title' => "Visible diagram {$index}",
            ]);
            DB::table('diagrams')->where('id', $diagram->id)->update([
                'updated_at' => now()->subMinutes($index),
            ]);
            $visible[] = $diagram;
        }

        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $hidden = Diagram::factory()->for($owner, 'author')->create([
            'title' => 'Hidden workspace diagram',
            'workspace_id' => $workspace->id,
        ]);

        $response = $this->actingAs($user)->get('/home');

        $response->assertOk()->assertDontSee($hidden->title);
        $diagrams = $response->viewData('diagrams');
        $this->assertCount(5, $diagrams);
        $this->assertSame(
            ['Visible diagram 1', 'Visible diagram 2', 'Visible diagram 3', 'Visible diagram 4', 'Visible diagram 5'],
            $diagrams->pluck('title')->all(),
        );
    }

    public function test_administrator_can_see_recent_diagrams_hidden_from_other_users(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $hidden = Diagram::factory()->for($owner, 'author')->create([
            'title' => 'Administrator-visible diagram',
            'workspace_id' => $workspace->id,
        ]);

        $user = User::factory()->create();
        $this->actingAs($user)->get('/home')->assertDontSee($hidden->title);

        $admin = User::factory()->create(['is_admin' => true]);
        $response = $this->actingAs($admin)->get('/home');

        $response->assertOk()->assertSee($hidden->title);
        $this->assertTrue($response->viewData('diagrams')->contains('id', $hidden->id));
    }

    public function test_home_shows_diagrams_the_user_can_open_as_collaborator_or_workspace_member(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();

        $collaborated = Diagram::factory()->for($owner, 'author')->create([
            'title' => 'Approved collaboration',
        ]);
        $collaborated->collaborators()->create([
            'user_id' => $user->id,
            'status' => Collaborator::STATUS_APPROVED,
        ]);

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->addMember($workspace, $owner);
        $this->addMember($workspace, $user);
        $memberDiagram = Diagram::factory()->for($owner, 'author')->create([
            'title' => 'Workspace membership',
            'workspace_id' => $workspace->id,
        ]);

        $pending = Diagram::factory()->for($owner, 'author')->create([
            'title' => 'Pending collaboration',
        ]);
        $pending->collaborators()->create([
            'user_id' => $user->id,
            'status' => Collaborator::STATUS_REQUESTED,
        ]);

        $response = $this->actingAs($user)->get('/home');

        $response->assertOk()
            ->assertSee($collaborated->title)
            ->assertSee($memberDiagram->title)
            ->assertDontSee($pending->title);
        $this->assertTrue($response->viewData('diagrams')->contains('id', $collaborated->id));
        $this->assertTrue($response->viewData('diagrams')->contains('id', $memberDiagram->id));
        $this->assertFalse($response->viewData('diagrams')->contains('id', $pending->id));
    }

    public function test_home_shows_an_empty_state_when_there_are_no_openable_diagrams(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/home')
            ->assertOk()
            ->assertSee(__('messages.home_no_recent_diagrams'));
    }

    public function test_a_search_hides_the_default_home_content_and_shows_only_the_results(): void
    {
        $user = User::factory()->create();
        Diagram::factory()->create(['owner_id' => $user->id, 'title' => 'Billing flow']);

        $this->actingAs($user)->get('/home?q=Billing')
            ->assertOk()
            ->assertSee(__('messages.search_results_heading'))
            ->assertSee('Billing flow')
            ->assertDontSee(__('messages.home_recent_diagrams'))
            ->assertDontSee(__('messages.home_shortcuts'));

        $this->actingAs($user)->get('/home')
            ->assertSee(__('messages.home_recent_diagrams'))
            ->assertSee(__('messages.home_shortcuts'));
    }

    #[DataProvider('supportedLocales')]
    public function test_home_translates_its_navigation_greeting_heading_and_shortcuts(
        string $locale,
        string $home,
        string $greeting,
        string $heading,
        string $workspaceShortcut,
        string $notificationShortcut,
    ): void {
        $user = User::factory()->create(['name' => 'Ada']);

        $this->actingAs($user)->withSession(['locale' => $locale])->get('/home')
            ->assertOk()
            ->assertSee($home)
            ->assertSee($greeting)
            ->assertSee($heading)
            ->assertSee($workspaceShortcut)
            ->assertSee($notificationShortcut);
    }

    public static function supportedLocales(): array
    {
        return [
            'English' => ['en', 'Home', 'Welcome, Ada!', 'Recently modified diagrams', 'Workspaces', 'Notification history'],
            'Brazilian Portuguese' => ['pt_BR', 'Início', 'Olá, Ada!', 'Diagramas modificados recentemente', 'Ambientes de Trabalho', 'Histórico de notificações'],
            'Spanish' => ['es', 'Inicio', '¡Hola, Ada!', 'Diagramas modificados recientemente', 'Espacios de trabajo', 'Historial de notificaciones'],
        ];
    }
}
