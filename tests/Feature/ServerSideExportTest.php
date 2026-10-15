<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\Share;
use App\Models\User;
use App\Rendering\DiagramRenderer;
use App\Rendering\ExportFormat;
use App\Rendering\RenderingFailedException;
use App\Rendering\TitleBlock;
use App\Repositories\VersionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeDiagramRenderer;
use Tests\TestCase;

class ServerSideExportTest extends TestCase
{
    use RefreshDatabase;

    private FakeDiagramRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('diagrams');
        $this->renderer = new FakeDiagramRenderer;
        $this->app->instance(DiagramRenderer::class, $this->renderer);
    }

    private function diagramFor(User $owner, string $content = '<bpmn:definitions/>'): Diagram
    {
        $diagram = Diagram::factory()->for($owner, 'author')->for($owner, 'owner')->create(['title' => 'Order process']);
        Storage::disk('diagrams')->put($diagram->disk_path, $content);

        return $diagram;
    }

    private function share(Diagram $diagram, array $attributes = []): Share
    {
        return Share::factory()->for($diagram)->for($diagram->owner, 'author')->create($attributes + ['allow_download' => true]);
    }

    public function test_share_with_download_serves_a_png(): void
    {
        $share = $this->share($this->diagramFor(User::factory()->create()));

        $response = $this->get("/s/{$share->token}/download/png");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertSame('attachment; filename="'.$share->diagram->slug.'.png"', $response->headers->get('Content-Disposition'));
        $this->assertSame('rendered-png', $response->getContent());
        $this->assertNull($this->renderer->calls[0]['titleBlock']);
    }

    public function test_share_with_download_serves_an_svg(): void
    {
        $share = $this->share($this->diagramFor(User::factory()->create()));

        $response = $this->get("/s/{$share->token}/download/svg");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertSame(ExportFormat::Svg, $this->renderer->calls[0]['format']);
    }

    public function test_share_pdf_carries_a_title_block_with_title_version_owner_and_date(): void
    {
        $owner = User::factory()->create(['name' => 'Maria Souza']);
        $diagram = $this->diagramFor($owner);
        $share = $this->share($diagram);

        $response = $this->get("/s/{$share->token}/download/pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $block = $this->renderer->calls[0]['titleBlock'];
        $this->assertSame('Order process', $block->title);
        $this->assertSame('Maria Souza', $block->owner);
        $this->assertSame(now()->toDateString(), $block->date);
        $this->assertSame('Version 1', $block->version);
    }

    public function test_pinned_share_pdf_names_the_pinned_version_and_renders_its_content(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->diagramFor($owner, '<bpmn:definitions>one</bpmn:definitions>');
        $this->actingAs($owner)->putJson("/diagrams/{$diagram->slug}/content", ['content' => '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">two</bpmn:definitions>'])->assertOk();
        $versions = $this->actingAs($owner)->getJson("/diagrams/{$diagram->slug}/versions")->json('versions');
        $oldest = collect($versions)->last();
        $this->putJson("/diagrams/{$diagram->slug}/versions/{$oldest['id']}/rename", ['name' => 'Approved by the board'])->assertOk();
        $share = $this->share($diagram, ['pinned_version_id' => $oldest['id']]);
        auth()->logout();

        $this->get("/s/{$share->token}/download/pdf")->assertOk();

        $call = $this->renderer->calls[0];
        $this->assertSame(app(VersionRepository::class)->get($diagram, $oldest['id']), $call['xml']);
        $this->assertNotSame(Storage::disk('diagrams')->get($diagram->disk_path), $call['xml']);
        $this->assertSame('Approved by the board (1)', $call['titleBlock']->version);
    }

    public function test_download_formats_are_refused_when_the_share_forbids_download(): void
    {
        $share = $this->share($this->diagramFor(User::factory()->create()), ['allow_download' => false]);

        foreach (['png', 'svg', 'pdf'] as $format) {
            $this->get("/s/{$share->token}/download/{$format}")->assertForbidden();
        }
        $this->assertSame([], $this->renderer->calls);
    }

    public function test_expired_or_revoked_shares_do_not_render(): void
    {
        $diagram = $this->diagramFor(User::factory()->create());
        $expired = $this->share($diagram, ['expires_at' => now()->subMinute()]);
        $revoked = $this->share($diagram, ['revoked_at' => now()]);

        $this->get("/s/{$expired->token}/download/png")->assertForbidden();
        $this->get("/s/{$revoked->token}/download/pdf")->assertForbidden();
        $this->assertSame([], $this->renderer->calls);
    }

    public function test_unknown_formats_are_not_found(): void
    {
        $share = $this->share($this->diagramFor(User::factory()->create()));

        $this->get("/s/{$share->token}/download/exe")->assertNotFound();
    }

    public function test_a_renderer_failure_answers_503(): void
    {
        $this->app->instance(DiagramRenderer::class, new class implements DiagramRenderer
        {
            public function render(string $bpmnXml, ExportFormat $format, ?TitleBlock $titleBlock = null): string
            {
                throw new RenderingFailedException('boom');
            }
        });
        $share = $this->share($this->diagramFor(User::factory()->create()));

        $this->get("/s/{$share->token}/download/png")->assertStatus(503);
    }

    public function test_share_download_urls_are_offered_for_every_format(): void
    {
        $share = $this->share($this->diagramFor(User::factory()->create()));

        foreach (['bpmn', 'png', 'svg', 'pdf'] as $format) {
            $this->assertSame(route('shares.download', ['share' => $share->token, 'format' => $format]), $share->downloadUrl($format));
        }
        $this->assertNull($this->share($share->diagram, ['allow_download' => false])->downloadUrl('pdf'));
    }

    public function test_public_viewer_links_directly_to_the_image_and_pdf_exports(): void
    {
        $share = $this->share($this->diagramFor(User::factory()->create(), '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"/>'));

        $response = $this->get($share->showUrl());

        foreach (['png', 'svg', 'pdf'] as $format) {
            $response->assertSee('href="'.route('shares.download', ['share' => $share->token, 'format' => $format]).'"', false);
        }
    }

    public function test_share_listing_exposes_the_download_links_per_format(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->diagramFor($owner);
        $this->share($diagram);

        $links = $this->actingAs($owner)->getJson("/diagrams/{$diagram->slug}/shares")->json('shares.0.download_urls');

        $this->assertSame(['bpmn', 'png', 'svg', 'pdf'], array_keys($links));
    }

    public function test_editor_user_exports_a_pdf_with_title_block(): void
    {
        $owner = User::factory()->create(['name' => 'Maria Souza']);
        $diagram = $this->diagramFor($owner);

        $response = $this->actingAs($owner)->get("/diagrams/{$diagram->slug}/export/pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('Maria Souza', $this->renderer->calls[0]['titleBlock']->owner);
    }

    public function test_editor_png_export_has_no_title_block(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->diagramFor($owner);

        $this->actingAs($owner)->get("/diagrams/{$diagram->slug}/export/png")->assertOk();

        $this->assertNull($this->renderer->calls[0]['titleBlock']);
    }

    public function test_export_requires_login_and_visibility(): void
    {
        $owner = User::factory()->create();
        $diagram = $this->diagramFor($owner);

        $this->get("/diagrams/{$diagram->slug}/export/pdf")->assertRedirect('/login');
        $this->actingAs($owner)->get("/diagrams/{$diagram->slug}/export/zip")->assertNotFound();
    }
}
