<?php

namespace Tests\Feature;

use App\Models\Compartilhamento;
use App\Models\Diagrama;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CompartilhamentoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
    }

    private function criarDiagrama(User $author, string $content = 'conteudo-bpmn'): Diagrama
    {
        $diagrama = Diagrama::factory()->for($author, 'author')->create();
        Storage::disk('diagrams')->put($diagrama->disk_path, $content);

        return $diagrama;
    }

    private function signedShowUrl(Compartilhamento $compartilhamento): string
    {
        return URL::temporarySignedRoute(
            'compartilhamentos.show',
            now()->addYears(10),
            ['compartilhamento' => $compartilhamento->id],
        );
    }

    private function signedDownloadUrl(Compartilhamento $compartilhamento, string $formato = 'bpmn'): string
    {
        return URL::temporarySignedRoute(
            'compartilhamentos.download',
            now()->addYears(10),
            ['compartilhamento' => $compartilhamento->id, 'formato' => $formato],
        );
    }

    public function test_author_can_create_compartilhamento_with_default_seven_days(): void
    {
        $author = User::factory()->create();
        $diagrama = $this->criarDiagrama($author);

        $response = $this->actingAs($author)->postJson("/diagramas/{$diagrama->slug}/compartilhamentos", []);

        $response->assertCreated();
        $response->assertJsonPath('allow_download', false);

        $compartilhamento = Compartilhamento::first();
        $this->assertNotNull($compartilhamento);
        $this->assertTrue($compartilhamento->expires_at->isSameDay(now()->addDays(7)));
    }

    public function test_author_can_create_compartilhamento_with_custom_days_and_download(): void
    {
        $author = User::factory()->create();
        $diagrama = $this->criarDiagrama($author);

        $response = $this->actingAs($author)->postJson("/diagramas/{$diagrama->slug}/compartilhamentos", [
            'dias' => 30,
            'allow_download' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('allow_download', true);
        $response->assertJsonStructure(['download_url']);

        $compartilhamento = Compartilhamento::first();
        $this->assertTrue($compartilhamento->expires_at->isSameDay(now()->addDays(30)));
        $this->assertTrue($compartilhamento->allow_download);
    }

    public function test_creation_rejects_less_than_one_day(): void
    {
        $author = User::factory()->create();
        $diagrama = $this->criarDiagrama($author);

        $response = $this->actingAs($author)->postJson("/diagramas/{$diagrama->slug}/compartilhamentos", [
            'dias' => 0,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('compartilhamentos', 0);
    }

    public function test_creation_rejects_more_than_sixty_days(): void
    {
        $author = User::factory()->create();
        $diagrama = $this->criarDiagrama($author);

        $response = $this->actingAs($author)->postJson("/diagramas/{$diagrama->slug}/compartilhamentos", [
            'dias' => 61,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('compartilhamentos', 0);
    }

    public function test_non_author_cannot_create_compartilhamento(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $diagrama = $this->criarDiagrama($author);

        $response = $this->actingAs($other)->postJson("/diagramas/{$diagrama->slug}/compartilhamentos", []);

        $response->assertStatus(403);
        $this->assertDatabaseCount('compartilhamentos', 0);
    }

    public function test_guest_can_view_diagram_with_valid_compartilhamento(): void
    {
        $author = User::factory()->create();
        $diagrama = $this->criarDiagrama($author, '<bpmn>conteudo</bpmn>');
        $compartilhamento = Compartilhamento::factory()->for($diagrama)->for($author, 'autor')->create([
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->get($this->signedShowUrl($compartilhamento));

        $response->assertOk();
        $response->assertSee($diagrama->title);
    }

    public function test_guest_sees_clear_message_when_compartilhamento_expired(): void
    {
        $author = User::factory()->create();
        $diagrama = $this->criarDiagrama($author);
        $compartilhamento = Compartilhamento::factory()->for($diagrama)->for($author, 'autor')->create([
            'expires_at' => now()->addDays(7),
        ]);

        $url = $this->signedShowUrl($compartilhamento);

        Carbon::setTestNow(now()->addDays(8));

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('expirou', false);

        Carbon::setTestNow();
    }

    public function test_guest_sees_clear_message_when_compartilhamento_revoked(): void
    {
        $author = User::factory()->create();
        $diagrama = $this->criarDiagrama($author);
        $compartilhamento = Compartilhamento::factory()->for($diagrama)->for($author, 'autor')->create([
            'expires_at' => now()->addDays(7),
            'revoked_at' => now(),
        ]);

        $response = $this->get($this->signedShowUrl($compartilhamento));

        $response->assertOk();
        $response->assertSee('revogado', false);
    }

    public function test_download_allowed_serves_the_bpmn_file(): void
    {
        $author = User::factory()->create();
        $diagrama = $this->criarDiagrama($author, '<bpmn>conteudo-para-baixar</bpmn>');
        $compartilhamento = Compartilhamento::factory()->for($diagrama)->for($author, 'autor')->create([
            'expires_at' => now()->addDays(7),
            'allow_download' => true,
        ]);

        $response = $this->get($this->signedDownloadUrl($compartilhamento));

        $response->assertOk();
        $response->assertSee('conteudo-para-baixar', false);
    }

    public function test_download_blocked_when_compartilhamento_disallows_it(): void
    {
        $author = User::factory()->create();
        $diagrama = $this->criarDiagrama($author);
        $compartilhamento = Compartilhamento::factory()->for($diagrama)->for($author, 'autor')->create([
            'expires_at' => now()->addDays(7),
            'allow_download' => false,
        ]);

        $response = $this->get($this->signedDownloadUrl($compartilhamento));

        $response->assertStatus(403);
    }

    public function test_download_blocked_when_compartilhamento_expired_even_if_allowed(): void
    {
        $author = User::factory()->create();
        $diagrama = $this->criarDiagrama($author);
        $compartilhamento = Compartilhamento::factory()->for($diagrama)->for($author, 'autor')->create([
            'expires_at' => now()->addDays(7),
            'allow_download' => true,
        ]);

        $url = $this->signedDownloadUrl($compartilhamento);

        Carbon::setTestNow(now()->addDays(8));

        $response = $this->get($url);

        $response->assertStatus(403);

        Carbon::setTestNow();
    }

    public function test_author_can_list_active_compartilhamentos_of_diagrama(): void
    {
        $author = User::factory()->create();
        $diagrama = $this->criarDiagrama($author);
        Compartilhamento::factory()->for($diagrama)->for($author, 'autor')->create(['allow_download' => true]);
        Compartilhamento::factory()->for($diagrama)->for($author, 'autor')->create(['allow_download' => false]);
        Compartilhamento::factory()->for($diagrama)->for($author, 'autor')->create(['revoked_at' => now()]);

        $response = $this->actingAs($author)->getJson("/diagramas/{$diagrama->slug}/compartilhamentos");

        $response->assertOk();
        $response->assertJsonCount(2, 'compartilhamentos');
    }

    public function test_non_author_cannot_list_compartilhamentos(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $diagrama = $this->criarDiagrama($author);
        Compartilhamento::factory()->for($diagrama)->for($author, 'autor')->create();

        $response = $this->actingAs($other)->getJson("/diagramas/{$diagrama->slug}/compartilhamentos");

        $response->assertStatus(403);
    }

    public function test_author_can_revoke_a_compartilhamento(): void
    {
        $author = User::factory()->create();
        $diagrama = $this->criarDiagrama($author);
        $compartilhamento = Compartilhamento::factory()->for($diagrama)->for($author, 'autor')->create();

        $response = $this->actingAs($author)->deleteJson("/compartilhamentos/{$compartilhamento->id}");

        $response->assertOk();
        $this->assertNotNull($compartilhamento->fresh()->revoked_at);

        $visit = $this->get($this->signedShowUrl($compartilhamento));
        $visit->assertSee('revogado', false);
    }

    public function test_non_author_cannot_revoke_a_compartilhamento(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $diagrama = $this->criarDiagrama($author);
        $compartilhamento = Compartilhamento::factory()->for($diagrama)->for($author, 'autor')->create();

        $response = $this->actingAs($other)->deleteJson("/compartilhamentos/{$compartilhamento->id}");

        $response->assertStatus(403);
        $this->assertNull($compartilhamento->fresh()->revoked_at);
    }

    public function test_diagrama_can_have_multiple_active_compartilhamentos_with_different_permissions(): void
    {
        $author = User::factory()->create();
        $diagrama = $this->criarDiagrama($author, '<bpmn>conteudo</bpmn>');

        $comDownload = Compartilhamento::factory()->for($diagrama)->for($author, 'autor')->create([
            'allow_download' => true,
            'expires_at' => now()->addDays(10),
        ]);
        $semDownload = Compartilhamento::factory()->for($diagrama)->for($author, 'autor')->create([
            'allow_download' => false,
            'expires_at' => now()->addDays(20),
        ]);

        $this->get($this->signedShowUrl($comDownload))->assertOk();
        $this->get($this->signedShowUrl($semDownload))->assertOk();

        $this->get($this->signedDownloadUrl($comDownload))->assertOk();
        $this->get($this->signedDownloadUrl($semDownload))->assertStatus(403);

        $list = $this->actingAs($author)->getJson("/diagramas/{$diagrama->slug}/compartilhamentos");
        $list->assertOk();
        $list->assertJsonCount(2, 'compartilhamentos');
    }
}
