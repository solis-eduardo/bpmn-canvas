<?php

namespace Tests\Feature;

use App\Models\Diagrama;
use App\Models\DiagramaColaborador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ColaboracaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_author_can_invite_a_collaborator_directly_with_immediate_access(): void
    {
        $author = User::factory()->create();
        $colaborador = User::factory()->create();
        $diagrama = Diagrama::factory()->for($author, 'author')->create();

        $response = $this->actingAs($author)->postJson("/diagramas/{$diagrama->slug}/colaboradores", [
            'user_id' => $colaborador->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('diagrama_colaboradores', [
            'diagrama_id' => $diagrama->id,
            'usuario_id' => $colaborador->id,
            'status' => 'aprovado',
        ]);

        $this->actingAs($colaborador)->get("/diagramas/{$diagrama->slug}")->assertOk();
    }

    public function test_author_can_invite_a_collaborator_by_email(): void
    {
        $author = User::factory()->create();
        $colaborador = User::factory()->create();
        $diagrama = Diagrama::factory()->for($author, 'author')->create();

        $response = $this->actingAs($author)->postJson("/diagramas/{$diagrama->slug}/colaboradores", [
            'email' => $colaborador->email,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('diagrama_colaboradores', [
            'diagrama_id' => $diagrama->id,
            'usuario_id' => $colaborador->id,
            'status' => 'aprovado',
        ]);
    }

    public function test_a_user_can_request_access_to_a_diagrama_that_is_not_theirs(): void
    {
        $author = User::factory()->create();
        $solicitante = User::factory()->create();
        $diagrama = Diagrama::factory()->for($author, 'author')->create();

        $response = $this->actingAs($solicitante)->postJson("/diagramas/{$diagrama->slug}/colaboradores", [
            'user_id' => $solicitante->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('diagrama_colaboradores', [
            'diagrama_id' => $diagrama->id,
            'usuario_id' => $solicitante->id,
            'status' => 'solicitado',
        ]);
    }

    public function test_a_pending_request_still_blocks_editing_until_approved(): void
    {
        $author = User::factory()->create();
        $solicitante = User::factory()->create();
        $diagrama = Diagrama::factory()->for($author, 'author')->create();

        $this->actingAs($solicitante)->postJson("/diagramas/{$diagrama->slug}/colaboradores", [
            'user_id' => $solicitante->id,
        ])->assertCreated();

        $this->actingAs($solicitante)->get("/diagramas/{$diagrama->slug}")->assertForbidden();
        $this->actingAs($solicitante)->getJson("/diagramas/{$diagrama->slug}/conteudo")->assertForbidden();
        $this->actingAs($solicitante)->putJson("/diagramas/{$diagrama->slug}/conteudo", [
            'content' => '<bpmn:definitions>x</bpmn:definitions>',
        ])->assertForbidden();
    }

    public function test_author_sees_and_approves_a_pending_request(): void
    {
        $author = User::factory()->create();
        $solicitante = User::factory()->create();
        $diagrama = Diagrama::factory()->for($author, 'author')->create();

        DiagramaColaborador::create([
            'diagrama_id' => $diagrama->id,
            'usuario_id' => $solicitante->id,
            'status' => 'solicitado',
        ]);

        $listing = $this->actingAs($author)->getJson("/diagramas/{$diagrama->slug}/colaboradores");
        $listing->assertOk();
        $listing->assertJsonFragment(['usuario_id' => $solicitante->id, 'status' => 'solicitado']);

        $approve = $this->actingAs($author)->putJson("/diagramas/{$diagrama->slug}/colaboradores/{$solicitante->id}");
        $approve->assertOk();

        $this->assertDatabaseHas('diagrama_colaboradores', [
            'diagrama_id' => $diagrama->id,
            'usuario_id' => $solicitante->id,
            'status' => 'aprovado',
        ]);

        $this->actingAs($solicitante)->get("/diagramas/{$diagrama->slug}")->assertOk();
    }

    public function test_author_can_reject_a_pending_request(): void
    {
        $author = User::factory()->create();
        $solicitante = User::factory()->create();
        $diagrama = Diagrama::factory()->for($author, 'author')->create();

        DiagramaColaborador::create([
            'diagrama_id' => $diagrama->id,
            'usuario_id' => $solicitante->id,
            'status' => 'solicitado',
        ]);

        $response = $this->actingAs($author)->deleteJson("/diagramas/{$diagrama->slug}/colaboradores/{$solicitante->id}");
        $response->assertOk();

        $this->assertDatabaseMissing('diagrama_colaboradores', [
            'diagrama_id' => $diagrama->id,
            'usuario_id' => $solicitante->id,
        ]);

        $this->actingAs($solicitante)->get("/diagramas/{$diagrama->slug}")->assertForbidden();
    }

    public function test_admin_can_grant_collaborator_access_on_a_diagrama_of_another_author(): void
    {
        $author = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $colaborador = User::factory()->create();
        $diagrama = Diagrama::factory()->for($author, 'author')->create();

        $response = $this->actingAs($admin)->postJson("/diagramas/{$diagrama->slug}/colaboradores", [
            'user_id' => $colaborador->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('diagrama_colaboradores', [
            'diagrama_id' => $diagrama->id,
            'usuario_id' => $colaborador->id,
            'status' => 'aprovado',
        ]);
    }

    public function test_author_can_remove_an_approved_collaborator_at_any_time(): void
    {
        $author = User::factory()->create();
        $colaborador = User::factory()->create();
        $diagrama = Diagrama::factory()->for($author, 'author')->create();

        DiagramaColaborador::create([
            'diagrama_id' => $diagrama->id,
            'usuario_id' => $colaborador->id,
            'status' => 'aprovado',
        ]);

        $this->actingAs($colaborador)->get("/diagramas/{$diagrama->slug}")->assertOk();

        $response = $this->actingAs($author)->deleteJson("/diagramas/{$diagrama->slug}/colaboradores/{$colaborador->id}");
        $response->assertOk();

        $this->assertDatabaseMissing('diagrama_colaboradores', [
            'diagrama_id' => $diagrama->id,
            'usuario_id' => $colaborador->id,
        ]);

        $this->actingAs($colaborador)->get("/diagramas/{$diagrama->slug}")->assertForbidden();
    }

    public function test_a_user_who_is_neither_author_nor_approved_collaborator_is_blocked_from_editing(): void
    {
        $author = User::factory()->create();
        $outsider = User::factory()->create();
        $diagrama = Diagrama::factory()->for($author, 'author')->create();

        $this->actingAs($outsider)->get("/diagramas/{$diagrama->slug}")->assertForbidden();
        $this->actingAs($outsider)->putJson("/diagramas/{$diagrama->slug}/conteudo", [
            'content' => '<bpmn:definitions>x</bpmn:definitions>',
        ])->assertForbidden();
    }

    public function test_the_general_listing_remains_visible_to_any_authenticated_user(): void
    {
        $author = User::factory()->create();
        $outsider = User::factory()->create();
        $diagrama = Diagrama::factory()->for($author, 'author')->create(['title' => 'Diagrama Alheio']);

        $response = $this->actingAs($outsider)->get('/diagramas');

        $response->assertOk();
        $response->assertSee('Diagrama Alheio');
    }

    public function test_a_non_author_non_admin_cannot_add_someone_else_as_collaborator(): void
    {
        $author = User::factory()->create();
        $outsider = User::factory()->create();
        $someoneElse = User::factory()->create();
        $diagrama = Diagrama::factory()->for($author, 'author')->create();

        $response = $this->actingAs($outsider)->postJson("/diagramas/{$diagrama->slug}/colaboradores", [
            'user_id' => $someoneElse->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('diagrama_colaboradores', [
            'diagrama_id' => $diagrama->id,
            'usuario_id' => $someoneElse->id,
        ]);
    }

    public function test_a_non_author_non_admin_cannot_approve_or_remove_collaborators(): void
    {
        $author = User::factory()->create();
        $outsider = User::factory()->create();
        $colaborador = User::factory()->create();
        $diagrama = Diagrama::factory()->for($author, 'author')->create();

        DiagramaColaborador::create([
            'diagrama_id' => $diagrama->id,
            'usuario_id' => $colaborador->id,
            'status' => 'solicitado',
        ]);

        $this->actingAs($outsider)->putJson("/diagramas/{$diagrama->slug}/colaboradores/{$colaborador->id}")
            ->assertForbidden();

        $this->actingAs($outsider)->deleteJson("/diagramas/{$diagrama->slug}/colaboradores/{$colaborador->id}")
            ->assertForbidden();
    }

    public function test_a_self_request_never_downgrades_an_already_approved_collaborator(): void
    {
        $author = User::factory()->create();
        $colaborador = User::factory()->create();
        $diagrama = Diagrama::factory()->for($author, 'author')->create();

        DiagramaColaborador::create([
            'diagrama_id' => $diagrama->id,
            'usuario_id' => $colaborador->id,
            'status' => DiagramaColaborador::STATUS_APROVADO,
        ]);

        $this->actingAs($colaborador)->postJson("/diagramas/{$diagrama->slug}/colaboradores", [
            'user_id' => $colaborador->id,
        ])->assertCreated()->assertJson(['status' => DiagramaColaborador::STATUS_APROVADO]);

        $this->assertDatabaseHas('diagrama_colaboradores', [
            'diagrama_id' => $diagrama->id,
            'usuario_id' => $colaborador->id,
            'status' => DiagramaColaborador::STATUS_APROVADO,
        ]);
    }
}
