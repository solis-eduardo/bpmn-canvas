<?php

namespace App\Rendering;

/**
 * Port for turning BPMN XML into an image or a PDF (issue #250). The adapter
 * is a headless bpmn-js run in a headless browser (NodeDiagramRenderer);
 * tests substitute a fake.
 */
interface DiagramRenderer
{
    /**
     * @param  TitleBlock|null  $titleBlock  only meaningful for PDF.
     * @return string the rendered bytes.
     *
     * @throws RenderingFailedException
     */
    public function render(string $bpmnXml, ExportFormat $format, ?TitleBlock $titleBlock = null): string;
}
