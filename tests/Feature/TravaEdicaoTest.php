<?php

namespace Tests\Feature;

use App\Models\Diagrama;
use App\Models\DiagramaColaborador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TravaEdicaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    /**
     * A Trava só é relevante entre quem já tem permissão de editar (Autor ou
     * Colaborador aprovado, ver ticket 06) — um Usuário sem essa permissão é
     * barrado por autorização antes mesmo de a Trava entrar em jogo.
     */
    private function approveCollaborator(Diagrama $diagrama, User $user): void
    {
        DiagramaColaborador::create([
            'diagrama_id' => $diagrama->id,
            'usuario_id' => $user->id,
            'status' => DiagramaColaborador::STATUS_APROVADO,
        ]);
    }

    public function test_opening_the_editor_without_an_active_lock_acquires_it_for_the_current_user(): void
    {
        $user = User::factory()->create();
        $diagrama = Diagrama::factory()->for($user, 'author')->create();

        $response = $this->actingAs($user)->get('/diagramas/'.$diagrama->slug);

        $response->assertOk();
        $this->assertSame($user->id, $diagrama->fresh()->locked_by);
        $this->assertNotNull($diagrama->fresh()->locked_at);
        $this->assertNotNull($diagrama->fresh()->last_heartbeat_at);
    }

    public function test_a_second_user_is_blocked_from_opening_an_actively_locked_diagrama_and_sees_who_holds_the_lock(): void
    {
        $holder = User::factory()->create(['name' => 'Ana Autora']);
        $other = User::factory()->create();
        $diagrama = Diagrama::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagrama, $other);

        $this->actingAs($holder)->get('/diagramas/'.$diagrama->slug);

        $response = $this->actingAs($other)->get('/diagramas/'.$diagrama->slug);

        $response->assertOk();
        $response->assertSee('Ana Autora');
        $this->assertSame($holder->id, $diagrama->fresh()->locked_by);
    }

    public function test_the_listing_indicates_which_diagramas_are_actively_locked_and_by_whom(): void
    {
        $holder = User::factory()->create(['name' => 'Bruno Bloqueante']);
        $viewer = User::factory()->create();
        $diagrama = Diagrama::factory()->for($holder, 'author')->create();
        $this->actingAs($holder)->get('/diagramas/'.$diagrama->slug);

        $response = $this->actingAs($viewer)->get('/diagramas');

        $response->assertOk();
        $response->assertSee('em edição por Bruno Bloqueante');
    }

    public function test_the_listing_does_not_indicate_a_lock_that_expired_due_to_inactivity(): void
    {
        $holder = User::factory()->create(['name' => 'Carla Cansada']);
        $viewer = User::factory()->create();
        $diagrama = Diagrama::factory()->for($holder, 'author')->create();
        $this->actingAs($holder)->get('/diagramas/'.$diagrama->slug);

        $this->travel(91)->seconds();

        $response = $this->actingAs($viewer)->get('/diagramas');

        $response->assertOk();
        $response->assertDontSee('em edição por');
    }

    public function test_explicitly_releasing_the_lock_allows_another_user_to_open_the_editor(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagrama = Diagrama::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagrama, $other);
        $this->actingAs($holder)->get('/diagramas/'.$diagrama->slug);

        $this->actingAs($holder)->deleteJson('/diagramas/'.$diagrama->slug.'/lock')->assertOk();

        $this->assertNull($diagrama->fresh()->locked_by);

        $response = $this->actingAs($other)->get('/diagramas/'.$diagrama->slug);

        $response->assertOk();
        $response->assertViewIs('diagramas.show');
        $this->assertSame($other->id, $diagrama->fresh()->locked_by);
    }

    public function test_the_lock_is_released_after_a_period_of_inactivity_without_heartbeat(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagrama = Diagrama::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagrama, $other);
        $this->actingAs($holder)->get('/diagramas/'.$diagrama->slug);

        $this->travel(91)->seconds();

        $response = $this->actingAs($other)->get('/diagramas/'.$diagrama->slug);

        $response->assertOk();
        $response->assertViewIs('diagramas.show');
        $this->assertSame($other->id, $diagrama->fresh()->locked_by);
    }

    public function test_heartbeat_keeps_the_lock_alive_past_the_inactivity_timeout(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagrama = Diagrama::factory()->for($holder, 'author')->create();
        $this->approveCollaborator($diagrama, $other);
        $this->actingAs($holder)->get('/diagramas/'.$diagrama->slug);

        $this->travel(60)->seconds();
        $this->actingAs($holder)->postJson('/diagramas/'.$diagrama->slug.'/heartbeat')->assertOk();
        $this->travel(60)->seconds();

        $response = $this->actingAs($other)->get('/diagramas/'.$diagrama->slug);

        $response->assertOk();
        $response->assertViewIs('diagramas.locked');
        $this->assertSame($holder->id, $diagrama->fresh()->locked_by);
    }

    public function test_only_the_current_lock_holder_can_send_a_heartbeat(): void
    {
        $holder = User::factory()->create();
        $other = User::factory()->create();
        $diagrama = Diagrama::factory()->for($holder, 'author')->create();
        $this->actingAs($holder)->get('/diagramas/'.$diagrama->slug);

        $response = $this->actingAs($other)->postJson('/diagramas/'.$diagrama->slug.'/heartbeat');

        $response->assertStatus(403);
    }

    public function test_forking_from_the_lock_screen_creates_an_independent_diagrama_with_copied_content(): void
    {
        $holder = User::factory()->create();
        $blocked = User::factory()->create();
        $diagrama = Diagrama::factory()->for($holder, 'author')->create();
        $this->actingAs($holder)->get('/diagramas/'.$diagrama->slug);
        Storage::disk('diagrams')->put($diagrama->disk_path, '<bpmn:definitions>conteúdo original</bpmn:definitions>');

        $response = $this->actingAs($blocked)->post('/diagramas/'.$diagrama->slug.'/fork');

        $response->assertRedirect();
        $forked = Diagrama::where('forked_from_id', $diagrama->id)->firstOrFail();
        $this->assertSame($blocked->id, $forked->author_id);
        $this->assertNotSame($diagrama->slug, $forked->slug);
        $this->assertNull($forked->locked_by);
        $this->assertSame(
            Storage::disk('diagrams')->get($diagrama->disk_path),
            Storage::disk('diagrams')->get($forked->disk_path)
        );
    }
}
