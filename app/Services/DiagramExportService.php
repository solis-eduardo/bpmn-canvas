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
     * The download for $format: the stored BPMN as is, or a server rendering.
     *
     * @param  string  $content  the BPMN XML to export.
     * @param  string|null  $versionId  the Version $content belongs to; null for the latest.
     */
    public function download(Diagram $diagram, string $content, ?string $versionId, string $format): Response
    {
        if ($format === 'bpmn') {
            return $this->respond($diagram, $content, 'application/xml', $format);
        }

        $exportFormat = ExportFormat::tryFrom($format) ?? abort(404);
        $titleBlock = $exportFormat === ExportFormat::Pdf ? $this->titleBlock($diagram, $versionId) : null;

        try {
            $bytes = $this->renderer->render($content, $exportFormat, $titleBlock);
        } catch (RenderingFailedException $e) {
            report($e);
            abort(503, __('messages.export_unavailable'));
        }

        return $this->respond($diagram, $bytes, $exportFormat->mimeType(), $format);
    }

    private function respond(Diagram $diagram, string $body, string $mimeType, string $extension): Response
    {
        return response($body, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="'.$diagram->slug.'.'.$extension.'"',
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
            : collect($history)->firstWhere('id', $this->versions->resolve($diagram, $versionId));

        if ($version === null) {
            return null;
        }

        return $version['renamed'] ? "{$version['name']} ({$version['number']})" : $version['name'];
    }
}
