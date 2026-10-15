<?php

namespace Tests\Unit\Rendering;

use App\Rendering\ExportFormat;
use App\Rendering\NodeDiagramRenderer;
use App\Rendering\RenderingFailedException;
use App\Rendering\TitleBlock;
use PHPUnit\Framework\TestCase;

class NodeDiagramRendererTest extends TestCase
{
    private string $script;

    protected function tearDown(): void
    {
        if (isset($this->script)) {
            @unlink($this->script);
        }
    }

    private function renderer(string $js, int $timeout = 10): NodeDiagramRenderer
    {
        $this->script = tempnam(sys_get_temp_dir(), 'renderer').'.mjs';
        file_put_contents($this->script, $js);

        return new NodeDiagramRenderer('node', $this->script, '/opt/chromium', $timeout);
    }

    public function test_it_sends_the_request_as_json_and_returns_stdout(): void
    {
        $echo = 'let s="";for await (const c of process.stdin) s+=c; process.stdout.write(s+"|"+process.env.CHROMIUM_PATH);';
        $block = new TitleBlock('Order', 'Version 2', 'Maria', '2026-10-15', ['version' => 'V', 'owner' => 'O', 'date' => 'D']);

        $out = $this->renderer($echo)->render('<xml/>', ExportFormat::Pdf, $block);

        [$json, $chromium] = explode('|', $out);
        $this->assertSame('/opt/chromium', $chromium);
        $this->assertSame(['xml' => '<xml/>', 'format' => 'pdf', 'title_block' => $block->toArray()], json_decode($json, true));
    }

    public function test_only_pdf_requests_carry_a_title_block(): void
    {
        $echo = 'let s="";for await (const c of process.stdin) s+=c; process.stdout.write(s);';
        $block = new TitleBlock('Order', null, 'Maria', '2026-10-15', ['version' => 'V', 'owner' => 'O', 'date' => 'D']);

        $out = $this->renderer($echo)->render('<xml/>', ExportFormat::Png, $block);

        $this->assertNull(json_decode($out, true)['title_block']);
    }

    public function test_a_failing_script_raises_rendering_failed(): void
    {
        $this->expectException(RenderingFailedException::class);
        $this->expectExceptionMessage('no chromium');

        $this->renderer('console.error("no chromium"); process.exit(1);')->render('<xml/>', ExportFormat::Png);
    }

    public function test_a_script_that_outputs_nothing_is_a_failure(): void
    {
        $this->expectException(RenderingFailedException::class);

        $this->renderer('')->render('<xml/>', ExportFormat::Svg);
    }

    public function test_a_hung_script_times_out(): void
    {
        $this->expectException(RenderingFailedException::class);
        $this->expectExceptionMessage('timed out');

        $this->renderer('setTimeout(() => {}, 60000);', 1)->render('<xml/>', ExportFormat::Svg);
    }
}
