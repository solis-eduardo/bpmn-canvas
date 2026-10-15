<?php

namespace Tests\Support;

use App\Rendering\DiagramRenderer;
use App\Rendering\ExportFormat;
use App\Rendering\TitleBlock;

/**
 * Stands in for the headless-browser renderer: records what it was asked to
 * render and answers with a recognizable payload.
 */
class FakeDiagramRenderer implements DiagramRenderer
{
    /** @var list<array{xml: string, format: ExportFormat, titleBlock: TitleBlock|null}> */
    public array $calls = [];

    public function render(string $bpmnXml, ExportFormat $format, ?TitleBlock $titleBlock = null): string
    {
        $this->calls[] = ['xml' => $bpmnXml, 'format' => $format, 'titleBlock' => $titleBlock];

        return 'rendered-'.$format->value;
    }
}
