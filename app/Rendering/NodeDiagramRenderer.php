<?php

namespace App\Rendering;

use Illuminate\Container\Attributes\Config;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Runs docker/renderer/render.mjs: bpmn-js inside headless Chromium. The
 * request goes in as JSON on stdin and the rendered bytes come out on stdout.
 */
class NodeDiagramRenderer implements DiagramRenderer
{
    public function __construct(
        #[Config('bpmn.renderer.node_binary')] private readonly string $nodeBinary,
        #[Config('bpmn.renderer.script')] private readonly string $script,
        #[Config('bpmn.renderer.chromium_path')] private readonly ?string $chromiumPath,
        #[Config('bpmn.renderer.timeout_seconds')] private readonly int $timeoutSeconds,
    ) {}

    public function render(string $bpmnXml, ExportFormat $format, ?TitleBlock $titleBlock = null): string
    {
        $process = new Process(
            [$this->nodeBinary, $this->script],
            env: $this->chromiumPath ? ['CHROMIUM_PATH' => $this->chromiumPath] : [],
        );
        $process->setTimeout($this->timeoutSeconds);
        $process->setInput(json_encode([
            'xml' => $bpmnXml,
            'format' => $format->value,
            'title_block' => $format === ExportFormat::Pdf ? $titleBlock?->toArray() : null,
        ], JSON_THROW_ON_ERROR));

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new RenderingFailedException('Rendering timed out.');
        }

        if (! $process->isSuccessful() || $process->getOutput() === '') {
            throw new RenderingFailedException('Rendering failed: '.trim($process->getErrorOutput()));
        }

        return $process->getOutput();
    }
}
