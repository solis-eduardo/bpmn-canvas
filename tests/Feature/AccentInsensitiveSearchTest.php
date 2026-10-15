<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use App\Repositories\VersionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Issue #247: search ignores accents in both directions (pt-BR users often
 * type without them). Runs on sqlite, where Diagram registers an unaccent()
 * SQL function mirroring the Postgres extension's behavior.
 */
class AccentInsensitiveSearchTest extends TestCase
{
    use RefreshDatabase;

    private const BPMN_ACCENTED = <<<'BPMN'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                  id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:task id="Task_1" name="Solicitação de Aprovação" />
  </bpmn:process>
</bpmn:definitions>
BPMN;

    /** @return list<int> */
    private function found(User $user, string $term): array
    {
        return $this->actingAs($user)->get('/diagrams?'.http_build_query(['q' => $term]))
            ->assertOk()->viewData('diagrams')->getCollection()->pluck('id')->all();
    }

    public function test_an_unaccented_term_finds_an_accented_title(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create(['title' => 'Aprovação de Crédito']);
        Diagram::factory()->for($user, 'author')->create(['title' => 'Unrelated']);

        $this->assertSame([$diagram->id], $this->found($user, 'aprovacao'));
    }

    public function test_an_accented_term_finds_an_unaccented_title(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create(['title' => 'Aprovacao de Credito']);

        $this->assertSame([$diagram->id], $this->found($user, 'APROVAÇÃO'));
    }

    public function test_an_unaccented_term_finds_accented_bpmn_content(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->for($user, 'author')->create(['title' => 'Procurement']);
        Storage::disk('diagrams')->put($diagram->disk_path, self::BPMN_ACCENTED);
        app(VersionRepository::class)->save($diagram, $user);

        $this->assertSame([$diagram->id], $this->found($user, 'solicitacao'));
        $this->assertSame([$diagram->id], $this->found($user, 'Aprovação'));
    }

    public function test_discovery_by_title_ignores_accents(): void
    {
        $diagram = Diagram::factory()->for(User::factory()->create(), 'author')->create(['title' => 'Solicitação de Férias']);

        $this->assertSame([$diagram->id], $this->found(User::factory()->create(), 'ferias'));
    }

    public function test_percent_stays_literal_with_accent_folding(): void
    {
        $user = User::factory()->create();
        Diagram::factory()->for($user, 'author')->create(['title' => 'Aprovação']);

        $this->assertSame([], $this->found($user, '%'));
    }
}
