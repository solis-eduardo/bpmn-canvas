<?php

namespace App\Services;

use App\Models\Diagram;
use App\Rendering\DiagramRenderer;
use App\Rendering\ExportFormat;
use App\Rendering\RenderingFailedException;
use App\Rendering\TitleBlock;
use App\Repositories\VersionRepository;
use Illuminate\Http\Response;

/**
 * Renders a Diagram's content on the server as PNG, SVG or PDF (issue #250).
 * The PDF gets a title block (title, Version, Diagram Owner, date).
 */
class DiagramExportService
{
    public function __construct(
        private readonly DiagramRenderer $renderer,
        private readonly VersionRepository $versions,
    ) {}

    /**
     * @param  string  $content  the BPMN XML to render.
     * @param  string|null  $versionId  the Version $content belongs to; null for the latest.
     *
     * @throws RenderingFailedException
     */
    public function export(Diagram $diagram, string $content, ?string $versionId, ExportFormat $format): Response
    {
        $titleBlock = $format === ExportFormat::Pdf ? $this->titleBlock($diagram, $versionId) : null;

        $bytes = $this->renderer->render($content, $format, $titleBlock);

        return response($bytes, 200, [
            'Content-Type' => $format->mimeType(),
            'Content-Disposition' => 'attachment; filename="'.$diagram->slug.'.'.$format->value.'"',
        ]);
    }

    /** The stored BPMN as is: no rendering involved. */
    public function exportBpmn(Diagram $diagram, string $content): Response
    {
        return response($content, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$diagram->slug.'.bpmn"',
        ]);
    }

    private function titleBlock(Diagram $diagram, ?string $versionId): TitleBlock
    {
        return new TitleBlock(
            title: $diagram->title,
            version: $this->versionLabel($diagram, $versionId),
            owner: $diagram->owner->name,
            date: now()->toDateString(),
            labels: [
                'version' => __('messages.export_version'),
                'owner' => __('messages.export_owner'),
                'date' => __('messages.export_date'),
            ],
        );
    }

    /** A renamed Version is named and numbered, so the number still identifies it. */
    private function versionLabel(Diagram $diagram, ?string $versionId): ?string
    {
        $history = $this->versions->list($diagram);
        $version = $versionId === null
            ? ($history[0] ?? null)
            : collect($history)->firstWhere('id', $versionId);

        if ($version === null) {
            return null;
        }

        return $version['renamed'] ? "{$version['name']} ({$version['number']})" : $version['name'];
    }
}
