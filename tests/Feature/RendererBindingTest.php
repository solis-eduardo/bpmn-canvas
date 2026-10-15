<?php

namespace Tests\Feature;

use App\Rendering\DiagramRenderer;
use App\Rendering\NodeDiagramRenderer;
use Tests\TestCase;

class RendererBindingTest extends TestCase
{
    public function test_the_renderer_port_resolves_to_the_node_adapter_with_the_configured_script(): void
    {
        config(['bpmn.renderer.script' => '/opt/render.mjs']);

        $renderer = app(DiagramRenderer::class);

        $this->assertInstanceOf(NodeDiagramRenderer::class, $renderer);
        $this->assertSame('/opt/render.mjs', (new \ReflectionProperty($renderer, 'script'))->getValue($renderer));
    }
}
