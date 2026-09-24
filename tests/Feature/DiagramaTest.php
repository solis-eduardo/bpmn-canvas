<?php

namespace Tests\Feature;

use App\Models\Diagrama;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DiagramaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_authenticated_user_can_create_a_diagrama_with_a_slug_derived_from_the_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/diagramas', [
            'title' => 'Processo de Compras',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('diagramas', [
            'title' => 'Processo de Compras',
            'slug' => 'processo-de-compras',
            'author_id' => $user->id,
        ]);

        $diagrama = Diagrama::where('slug', 'processo-de-compras')->firstOrFail();
        Storage::disk('diagrams')->assertExists($diagrama->disk_path);
    }

    public function test_guest_cannot_create_a_diagrama(): void
    {
        $response = $this->post('/diagramas', ['title' => 'Sem Login']);

        $response->assertRedirect('/login');
        $this->assertDatabaseMissing('diagramas', ['title' => 'Sem Login']);
    }

    public function test_authenticated_user_sees_a_listing_of_all_diagramas(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Diagrama::factory()->for($other, 'author')->create(['title' => 'Diagrama de Outro Usuário']);

        $response = $this->actingAs($user)->get('/diagramas');

        $response->assertOk();
        $response->assertSee('Diagrama de Outro Usuário');
    }

    public function test_user_can_open_the_editor_of_an_existing_diagrama_by_its_current_slug(): void
    {
        $user = User::factory()->create();
        $diagrama = Diagrama::factory()->for($user, 'author')->create();

        $response = $this->actingAs($user)->get('/diagramas/'.$diagrama->slug);

        $response->assertOk();
        $response->assertSee($diagrama->title);
    }

    public function test_user_can_fetch_the_current_content_of_a_diagrama(): void
    {
        $user = User::factory()->create();
        $diagrama = Diagrama::factory()->for($user, 'author')->create();

        $response = $this->actingAs($user)->getJson('/diagramas/'.$diagrama->slug.'/conteudo');

        $response->assertOk();
        $response->assertJson(['content' => Diagrama::EMPTY_BPMN]);
    }

    public function test_saving_a_diagrama_overwrites_the_content_on_disk(): void
    {
        $user = User::factory()->create();
        $diagrama = Diagrama::factory()->for($user, 'author')->create();
        $newContent = '<bpmn:definitions>novo conteúdo</bpmn:definitions>';

        $response = $this->actingAs($user)->putJson('/diagramas/'.$diagrama->slug.'/conteudo', [
            'content' => $newContent,
        ]);

        $response->assertOk();
        Storage::disk('diagrams')->assertExists($diagrama->disk_path);
        $this->assertSame($newContent, Storage::disk('diagrams')->get($diagrama->disk_path));
    }

    public function test_renaming_a_diagrama_updates_slug_and_the_old_slug_keeps_redirecting(): void
    {
        $user = User::factory()->create();
        $diagrama = Diagrama::factory()->for($user, 'author')->create(['title' => 'Nome Original']);
        $originalSlug = $diagrama->slug;

        $response = $this->actingAs($user)->putJson('/diagramas/'.$originalSlug, [
            'title' => 'Novo Nome',
        ]);

        $response->assertOk();
        $diagrama->refresh();
        $this->assertSame('novo-nome', $diagrama->slug);
        $this->assertSame('Novo Nome', $diagrama->title);

        $this->assertDatabaseHas('diagrama_slugs_antigos', [
            'diagrama_id' => $diagrama->id,
            'slug_antigo' => $originalSlug,
        ]);

        Storage::disk('diagrams')->assertMissing($originalSlug.'/diagram.bpmn');
        Storage::disk('diagrams')->assertExists($diagrama->disk_path);

        $redirect = $this->actingAs($user)->get('/diagramas/'.$originalSlug);
        $redirect->assertRedirect('/diagramas/novo-nome');
    }

    public function test_old_slug_still_redirects_after_multiple_renames_in_sequence(): void
    {
        $user = User::factory()->create();
        $diagrama = Diagrama::factory()->for($user, 'author')->create(['title' => 'Primeiro Nome']);
        $firstSlug = $diagrama->slug;

        $this->actingAs($user)->putJson('/diagramas/'.$firstSlug, ['title' => 'Segundo Nome'])->assertOk();
        $diagrama->refresh();
        $secondSlug = $diagrama->slug;

        $this->actingAs($user)->putJson('/diagramas/'.$secondSlug, ['title' => 'Terceiro Nome'])->assertOk();
        $diagrama->refresh();

        $this->actingAs($user)->get('/diagramas/'.$firstSlug)
            ->assertRedirect('/diagramas/'.$diagrama->slug);

        $this->actingAs($user)->get('/diagramas/'.$secondSlug)
            ->assertRedirect('/diagramas/'.$diagrama->slug);
    }

    public function test_an_old_slug_is_never_reused_by_a_different_diagrama(): void
    {
        $user = User::factory()->create();
        $diagrama = Diagrama::factory()->for($user, 'author')->create(['title' => 'Nome Antigo']);
        $oldSlug = $diagrama->slug;

        $this->actingAs($user)->putJson('/diagramas/'.$oldSlug, ['title' => 'Nome Novo'])->assertOk();

        $response = $this->actingAs($user)->post('/diagramas', ['title' => 'Nome Antigo']);
        $response->assertRedirect();

        $novoDiagrama = Diagrama::where('title', 'Nome Antigo')->firstOrFail();
        $this->assertNotSame($oldSlug, $novoDiagrama->slug);
    }

    public function test_creating_a_diagrama_with_a_colliding_slug_automatically_gets_a_suffix(): void
    {
        $user = User::factory()->create();
        Diagrama::factory()->for($user, 'author')->create(['title' => 'Processo Padrão', 'slug' => 'processo-padrao']);

        $response = $this->actingAs($user)->post('/diagramas', ['title' => 'Processo Padrão']);
        $response->assertRedirect();

        $diagramas = Diagrama::where('title', 'Processo Padrão')->get();
        $this->assertCount(2, $diagramas);
        $slugs = $diagramas->pluck('slug')->all();
        $this->assertContains('processo-padrao', $slugs);
        $this->assertNotEquals(['processo-padrao', 'processo-padrao'], $slugs);
    }

    public function test_renaming_a_diagrama_to_a_slug_reserved_as_someone_elses_old_slug_gets_a_suffix(): void
    {
        $user = User::factory()->create();

        $diagramaA = Diagrama::factory()->for($user, 'author')->create(['title' => 'Nome Reservado', 'slug' => 'nome-reservado']);
        $this->actingAs($user)->putJson('/diagramas/'.$diagramaA->slug, ['title' => 'Outro Nome'])->assertOk();
        $diagramaA->refresh();
        $this->assertDatabaseHas('diagrama_slugs_antigos', ['slug_antigo' => 'nome-reservado']);

        $diagramaB = Diagrama::factory()->for($user, 'author')->create(['title' => 'Diagrama B']);

        $response = $this->actingAs($user)->putJson('/diagramas/'.$diagramaB->slug, ['title' => 'Nome Reservado']);
        $response->assertOk();

        $diagramaB->refresh();
        $this->assertNotSame('nome-reservado', $diagramaB->slug);
        $this->assertSame('Nome Reservado', $diagramaB->title);
    }

    public function test_deleting_a_diagrama_removes_access_via_editor_and_any_associated_slug(): void
    {
        $user = User::factory()->create();
        $diagrama = Diagrama::factory()->for($user, 'author')->create(['title' => 'Para Excluir']);
        $currentSlug = $diagrama->slug;

        $this->actingAs($user)->putJson('/diagramas/'.$currentSlug, ['title' => 'Para Excluir Renomeado'])->assertOk();
        $diagrama->refresh();
        $renamedSlug = $diagrama->slug;

        $this->actingAs($user)->delete('/diagramas/'.$renamedSlug)->assertOk();

        $this->assertDatabaseMissing('diagramas', ['id' => $diagrama->id]);
        $this->assertDatabaseMissing('diagrama_slugs_antigos', ['diagrama_id' => $diagrama->id]);

        $this->actingAs($user)->get('/diagramas/'.$renamedSlug)->assertNotFound();
        $this->actingAs($user)->get('/diagramas/'.$currentSlug)->assertNotFound();
        $this->actingAs($user)->getJson('/diagramas/'.$renamedSlug.'/conteudo')->assertNotFound();
    }
}
