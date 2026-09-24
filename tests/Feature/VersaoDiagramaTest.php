<?php

namespace Tests\Feature;

use App\Models\Diagrama;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VersaoDiagramaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    public function test_creating_a_diagrama_generates_an_initial_version(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/diagramas', ['title' => 'Processo Versionado'])->assertRedirect();

        $diagrama = Diagrama::where('slug', 'processo-versionado')->firstOrFail();

        $response = $this->actingAs($user)->getJson('/diagramas/'.$diagrama->slug.'/versoes');

        $response->assertOk();
        $this->assertCount(1, $response->json('versoes.0.itens'));
    }

    public function test_multiple_saves_generate_multiple_versions_without_discarding_any(): void
    {
        $user = User::factory()->create();
        $diagrama = Diagrama::factory()->for($user, 'author')->create();

        $this->actingAs($user)->putJson('/diagramas/'.$diagrama->slug.'/conteudo', [
            'content' => '<bpmn:definitions>versao um</bpmn:definitions>',
        ])->assertOk();

        $this->actingAs($user)->putJson('/diagramas/'.$diagrama->slug.'/conteudo', [
            'content' => '<bpmn:definitions>versao dois</bpmn:definitions>',
        ])->assertOk();

        $this->actingAs($user)->putJson('/diagramas/'.$diagrama->slug.'/conteudo', [
            'content' => '<bpmn:definitions>versao tres</bpmn:definitions>',
        ])->assertOk();

        $response = $this->actingAs($user)->getJson('/diagramas/'.$diagrama->slug.'/versoes');

        $response->assertOk();
        $itens = collect($response->json('versoes'))->flatMap(fn ($dia) => $dia['itens']);

        // Versão inicial (criação via factory, que também gera uma versão) + 3 saves.
        $this->assertGreaterThanOrEqual(4, $itens->count());
    }

    public function test_version_history_is_grouped_by_calendar_day(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00'));
        $diagrama = Diagrama::factory()->for($user, 'author')->create();

        Carbon::setTestNow(Carbon::parse('2026-09-20 11:00:00'));
        $this->actingAs($user)->putJson('/diagramas/'.$diagrama->slug.'/conteudo', [
            'content' => '<bpmn:definitions>dia 1 - segunda</bpmn:definitions>',
        ])->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-09-21 09:00:00'));
        $this->actingAs($user)->putJson('/diagramas/'.$diagrama->slug.'/conteudo', [
            'content' => '<bpmn:definitions>dia 2</bpmn:definitions>',
        ])->assertOk();

        $response = $this->actingAs($user)->getJson('/diagramas/'.$diagrama->slug.'/versoes');
        Carbon::setTestNow();

        $response->assertOk();
        $dias = collect($response->json('versoes'))->pluck('dia')->all();

        $this->assertContains('2026-09-20', $dias);
        $this->assertContains('2026-09-21', $dias);

        $diaEsperado = collect($response->json('versoes'))->firstWhere('dia', '2026-09-20');
        $this->assertCount(2, $diaEsperado['itens']);
    }

    public function test_restoring_an_old_version_makes_content_match_it_and_creates_a_new_version_on_top(): void
    {
        $user = User::factory()->create();
        $diagrama = Diagrama::factory()->for($user, 'author')->create();

        $conteudoOriginal = Storage::disk('diagrams')->get($diagrama->disk_path);

        $this->actingAs($user)->putJson('/diagramas/'.$diagrama->slug.'/conteudo', [
            'content' => '<bpmn:definitions>versao intermediaria</bpmn:definitions>',
        ])->assertOk();

        $this->actingAs($user)->putJson('/diagramas/'.$diagrama->slug.'/conteudo', [
            'content' => '<bpmn:definitions>versao mais recente</bpmn:definitions>',
        ])->assertOk();

        $historico = $this->actingAs($user)->getJson('/diagramas/'.$diagrama->slug.'/versoes')->json('versoes');
        $todasVersoes = collect($historico)->flatMap(fn ($dia) => $dia['itens']);

        // A versão mais antiga (conteúdo original de criação) é o último item.
        $versaoOriginal = $todasVersoes->last();
        $qtdVersoesAntesDoRestore = $todasVersoes->count();

        $restaurar = $this->actingAs($user)->postJson(
            '/diagramas/'.$diagrama->slug.'/versoes/'.$versaoOriginal['id'].'/restaurar'
        );
        $restaurar->assertOk();

        $conteudoAtual = $this->actingAs($user)->getJson('/diagramas/'.$diagrama->slug.'/conteudo')->json('content');
        $this->assertSame($conteudoOriginal, $conteudoAtual);

        $historicoDepois = $this->actingAs($user)->getJson('/diagramas/'.$diagrama->slug.'/versoes')->json('versoes');
        $todasVersoesDepois = collect($historicoDepois)->flatMap(fn ($dia) => $dia['itens']);

        // Uma nova versão foi criada no topo do histórico.
        $this->assertSame($qtdVersoesAntesDoRestore + 1, $todasVersoesDepois->count());

        // O histórico intermediário continua existindo/visível (nada foi apagado).
        $idsDepois = $todasVersoesDepois->pluck('id')->all();
        foreach ($todasVersoes->pluck('id') as $idAntigo) {
            $this->assertContains($idAntigo, $idsDepois);
        }
    }

    public function test_no_api_response_exposes_git_vocabulary(): void
    {
        $user = User::factory()->create();
        $diagrama = Diagrama::factory()->for($user, 'author')->create();

        $this->actingAs($user)->putJson('/diagramas/'.$diagrama->slug.'/conteudo', [
            'content' => '<bpmn:definitions>outra versao</bpmn:definitions>',
        ])->assertOk();

        $response = $this->actingAs($user)->getJson('/diagramas/'.$diagrama->slug.'/versoes');
        $response->assertOk();

        $body = $response->getContent();
        $this->assertStringNotContainsStringIgnoringCase('commit', $body);
        $this->assertStringNotContainsStringIgnoringCase('branch', $body);
        $this->assertStringNotContainsStringIgnoringCase('git', $body);
    }

    public function test_a_user_without_permission_cannot_view_or_restore_versions(): void
    {
        $author = User::factory()->create();
        $outsider = User::factory()->create();
        $diagrama = Diagrama::factory()->for($author, 'author')->create();

        $this->actingAs($outsider)->getJson('/diagramas/'.$diagrama->slug.'/versoes')->assertForbidden();
        $this->actingAs($outsider)->postJson('/diagramas/'.$diagrama->slug.'/versoes/deadbeef/restaurar')->assertForbidden();
    }
}
